<?php
/**
 * Import/export admin page and handlers (RC-044, RC-045).
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\ImportExport;

use ReviewCaptain\Domain\Kind;
use ReviewCaptain\Domain\Platform;
use ReviewCaptain\Domain\ReviewStatus;
use ReviewCaptain\Module;
use ReviewCaptain\Plugin;
use ReviewCaptain\Security\Capabilities;

/**
 * Submenu "Import & Export" below the Review Captain top-level menu.
 */
final class ImportExportModule implements Module {

	public const PAGE = 'review-captain-import-export';

	public const PARENT = 'review-captain';

	/**
	 * Transient lifetime of a pending dry run.
	 */
	public const PENDING_TTL = HOUR_IN_SECONDS;

	private ?Plugin $plugin = null;

	/**
	 * Register hooks.
	 *
	 * @param Plugin $plugin Plugin.
	 */
	public function register( Plugin $plugin ): void {
		$this->plugin = $plugin;
		$plugin->factory( 'csv_exporter', static fn( Plugin $p ) => new CsvExporter( $p->reviews() ) );
		$plugin->factory( 'csv_importer', static fn( Plugin $p ) => new CsvImporter( $p->reviews() ) );
		add_action( 'admin_menu', array( $this, 'menu' ), 30 );
		add_action( 'admin_post_review_captain_export', array( $this, 'handle_export' ) );
		add_action( 'admin_post_review_captain_import_upload', array( $this, 'handle_upload' ) );
		add_action( 'admin_post_review_captain_import_confirm', array( $this, 'handle_confirm' ) );
		add_action( 'admin_post_review_captain_import_cancel', array( $this, 'handle_cancel' ) );
		add_action( 'admin_post_review_captain_import_rollback', array( $this, 'handle_rollback' ) );
	}

	/**
	 * Add the submenu page.
	 */
	public function menu(): void {
		add_submenu_page(
			self::PARENT,
			__( 'Import & export', 'review-captain' ),
			__( 'Import & export', 'review-captain' ),
			Capabilities::MANAGE_PRIVACY,
			self::PAGE,
			array( $this, 'render' )
		);
	}

	/**
	 * Plugin instance.
	 */
	private function plugin(): Plugin {
		return $this->plugin ?? Plugin::instance();
	}

	/**
	 * Exporter.
	 */
	private function exporter(): CsvExporter {
		/** @var CsvExporter */
		return $this->plugin()->has( 'csv_exporter' ) ? $this->plugin()->get( 'csv_exporter' ) : new CsvExporter( $this->plugin()->reviews() );
	}

	/**
	 * Importer.
	 */
	public function importer(): CsvImporter {
		/** @var CsvImporter */
		return $this->plugin()->has( 'csv_importer' ) ? $this->plugin()->get( 'csv_importer' ) : new CsvImporter( $this->plugin()->reviews() );
	}

	/**
	 * Sanitised export filters from a request array.
	 *
	 * @param array<string, mixed> $input Raw input (unslashed).
	 * @return array<string, string>
	 */
	public static function export_filters( array $input ): array {
		$filters = array();
		$enums   = array(
			'kind'     => Kind::values(),
			'platform' => Platform::values(),
			'status'   => ReviewStatus::values(),
		);
		foreach ( $enums as $key => $allowed ) {
			$value = isset( $input[ $key ] ) ? sanitize_key( (string) $input[ $key ] ) : '';
			if ( in_array( $value, $allowed, true ) ) {
				$filters[ $key ] = $value;
			}
		}
		foreach ( array( 'date_from', 'date_to' ) as $key ) {
			$value = isset( $input[ $key ] ) ? (string) $input[ $key ] : '';
			if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
				$filters[ $key ] = $value;
			}
		}
		return $filters;
	}

	/**
	 * Export handler: streams the CSV.
	 */
	public function handle_export(): void {
		Capabilities::verify_request( Capabilities::MANAGE_PRIVACY, 'export' );
		$input            = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
		$filters          = self::export_filters( is_array( $input ) ? $input : array() );
		$include_personal = ! empty( $input['include_personal'] );
		$count            = $this->exporter()->stream( $filters, $include_personal );
		$this->plugin()->audit()->record(
			'export.created',
			'reviews',
			'',
			array(
				'count' => $count,
				'scope' => $include_personal ? 'personal' : 'default',
			)
		);
		exit;
	}

	/**
	 * Upload handler: validates the file and stores a dry run for confirmation.
	 */
	public function handle_upload(): void {
		Capabilities::verify_request( Capabilities::MANAGE_PRIVACY, 'import_upload' );
		if ( ! current_user_can( Capabilities::MODERATE ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'review-captain' ), '', array( 'response' => 403 ) );
		}
		$file  = isset( $_FILES['rc_import_file'] ) && is_array( $_FILES['rc_import_file'] ) ? $_FILES['rc_import_file'] : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validated by UploadStore.
		$error = UploadStore::validate( $file );
		if ( null !== $error ) {
			$this->redirect( array( 'rc_notice' => 'upload_' . $error ) );
		}
		UploadStore::purge_older_than( HOUR_IN_SECONDS );
		$path = UploadStore::store( $file );
		if ( null === $path ) {
			$this->redirect( array( 'rc_notice' => 'upload_upload_failed' ) );
		}
		$this->discard_pending();
		$result           = $this->importer()->dry_run( (string) $path );
		$result['errors'] = array_slice( $result['errors'], 0, 200 );
		set_transient(
			self::pending_key(),
			array(
				'token'    => bin2hex( random_bytes( 16 ) ),
				'path'     => $path,
				'filename' => sanitize_file_name( (string) $file['name'] ),
				'result'   => $result,
			),
			self::PENDING_TTL
		);
		$this->redirect( array( 'rc_notice' => 'dry_run' ) );
	}

	/**
	 * Confirm handler: runs the import of the pending file.
	 */
	public function handle_confirm(): void {
		Capabilities::verify_request( Capabilities::MANAGE_PRIVACY, 'import_confirm' );
		if ( ! current_user_can( Capabilities::MODERATE ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'review-captain' ), '', array( 'response' => 403 ) );
		}
		$pending = get_transient( self::pending_key() );
		$token   = isset( $_POST['token'] ) ? sanitize_key( wp_unslash( (string) $_POST['token'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
		if ( ! is_array( $pending ) || ! hash_equals( (string) ( $pending['token'] ?? '' ), $token ) || ! UploadStore::owns( (string) $pending['path'] ) ) {
			$this->redirect( array( 'rc_notice' => 'import_expired' ) );
		}
		$result = $this->importer()->import( (string) $pending['path'], get_current_user_id(), (string) $pending['filename'] );
		$this->discard_pending();
		$this->redirect(
			array(
				'rc_notice'   => $result['batch_id'] > 0 ? 'imported' : 'import_failed',
				'rc_imported' => $result['imported'],
				'rc_skipped'  => $result['skipped'],
			)
		);
	}

	/**
	 * Cancel handler: deletes the pending file.
	 */
	public function handle_cancel(): void {
		Capabilities::verify_request( Capabilities::MANAGE_PRIVACY, 'import_cancel' );
		$this->discard_pending();
		$this->redirect( array( 'rc_notice' => 'cancelled' ) );
	}

	/**
	 * Rollback handler.
	 */
	public function handle_rollback(): void {
		Capabilities::verify_request( Capabilities::MANAGE_PRIVACY, 'import_rollback' );
		$batch_id = isset( $_POST['batch_id'] ) ? absint( wp_unslash( $_POST['batch_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
		$confirm  = ! empty( $_POST['confirm_modified'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
		$importer = $this->importer();
		$modified = $importer->modified_since_import( $batch_id );
		if ( $modified > 0 && ! $confirm ) {
			$this->redirect(
				array(
					'rc_notice'   => 'rollback_confirm',
					'rc_batch'    => $batch_id,
					'rc_modified' => $modified,
				)
			);
		}
		$deleted = $importer->rollback( $batch_id );
		$this->redirect(
			array(
				'rc_notice'  => 'rolled_back',
				'rc_deleted' => $deleted,
			)
		);
	}

	/**
	 * Delete a pending upload of the current user.
	 */
	private function discard_pending(): void {
		$pending = get_transient( self::pending_key() );
		if ( is_array( $pending ) && isset( $pending['path'] ) ) {
			UploadStore::delete( (string) $pending['path'] );
		}
		delete_transient( self::pending_key() );
	}

	/**
	 * Transient key of the current user's pending import.
	 */
	private static function pending_key(): string {
		return 'review_captain_import_' . get_current_user_id();
	}

	/**
	 * Redirect back to the page and stop.
	 *
	 * @param array<string, scalar> $args Query args.
	 * @return never
	 */
	private function redirect( array $args ): never {
		wp_safe_redirect( add_query_arg( $args, self::page_url() ) );
		exit;
	}

	/**
	 * Page URL.
	 */
	public static function page_url(): string {
		return admin_url( 'admin.php?page=' . self::PAGE );
	}

	/**
	 * Render the page.
	 */
	public function render(): void {
		if ( ! current_user_can( Capabilities::MANAGE_PRIVACY ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'review-captain' ), '', array( 'response' => 403 ) );
		}
		$post_url = admin_url( 'admin-post.php' );
		echo '<div class="wrap rc-import-export"><h1>' . esc_html__( 'Import & export', 'review-captain' ) . '</h1>';
		$this->render_notice();

		// Export.
		echo '<h2>' . esc_html__( 'Export', 'review-captain' ) . '</h2>';
		echo '<p>' . esc_html__( 'Exports reviews and testimonials as CSV (UTF-8) in the import format. Email addresses are only exported if you explicitly select this.', 'review-captain' ) . '</p>';
		echo '<form method="post" action="' . esc_url( $post_url ) . '">';
		echo '<input type="hidden" name="action" value="review_captain_export" />';
		wp_nonce_field( Capabilities::nonce_action( 'export' ), '_rc_nonce' );
		echo '<table class="form-table" role="presentation"><tbody>';
		$this->select_row( 'kind', __( 'Type', 'review-captain' ), array_combine( Kind::values(), array_map( static fn( Kind $k ): string => $k->label(), Kind::cases() ) ) );
		$this->select_row( 'platform', __( 'Platform', 'review-captain' ), array_combine( Platform::values(), array_map( static fn( Platform $p ): string => $p->label(), Platform::cases() ) ) );
		$this->select_row( 'status', __( 'Status', 'review-captain' ), array_combine( ReviewStatus::values(), array_map( static fn( ReviewStatus $s ): string => $s->label(), ReviewStatus::cases() ) ) );
		echo '<tr><th scope="row">' . esc_html__( 'Date range (created)', 'review-captain' ) . '</th><td>';
		echo '<label>' . esc_html__( 'from', 'review-captain' ) . ' <input type="date" name="date_from" /></label> ';
		echo '<label>' . esc_html__( 'to', 'review-captain' ) . ' <input type="date" name="date_to" /></label></td></tr>';
		echo '<tr><th scope="row">' . esc_html__( 'Personal data', 'review-captain' ) . '</th><td><label><input type="checkbox" name="include_personal" value="1" /> ' . esc_html__( 'Include personal data (authors\' email addresses)', 'review-captain' ) . '</label></td></tr>';
		echo '</tbody></table>';
		echo '<p><button type="submit" class="button button-primary">' . esc_html__( 'Export CSV', 'review-captain' ) . '</button></p></form>';

		// Import.
		echo '<h2>' . esc_html__( 'Import', 'review-captain' ) . '</h2>';
		$pending = get_transient( self::pending_key() );
		if ( is_array( $pending ) && isset( $pending['result'] ) ) {
			$this->render_pending( $pending, $post_url );
		} else {
			echo '<p>' . esc_html__( 'Upload a CSV file (max. 5 MB, 10,000 rows). A dry run is always shown before the import. Purchase or attendance verifications are not imported.', 'review-captain' ) . '</p>';
			echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( $post_url ) . '">';
			echo '<input type="hidden" name="action" value="review_captain_import_upload" />';
			wp_nonce_field( Capabilities::nonce_action( 'import_upload' ), '_rc_nonce' );
			echo '<p><label for="rc-import-file">' . esc_html__( 'CSV file', 'review-captain' ) . '</label> <input type="file" id="rc-import-file" name="rc_import_file" accept=".csv,text/csv" required /></p>';
			echo '<p><button type="submit" class="button">' . esc_html__( 'Start dry run', 'review-captain' ) . '</button></p></form>';
		}

		// Batches.
		echo '<h2>' . esc_html__( 'Import runs', 'review-captain' ) . '</h2>';
		$batches = $this->importer()->batches( 50 );
		if ( ! $batches ) {
			echo '<p>' . esc_html__( 'No imports yet.', 'review-captain' ) . '</p></div>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'File', 'review-captain' ) . '</th><th>' . esc_html__( 'Created (UTC)', 'review-captain' ) . '</th><th>' . esc_html__( 'Rows', 'review-captain' ) . '</th><th>' . esc_html__( 'Imported', 'review-captain' ) . '</th><th>' . esc_html__( 'Skipped', 'review-captain' ) . '</th><th>' . esc_html__( 'Status', 'review-captain' ) . '</th><th>' . esc_html__( 'Action', 'review-captain' ) . '</th></tr></thead><tbody>';
		foreach ( $batches as $batch ) {
			echo '<tr><td>' . esc_html( (string) $batch['filename'] ) . '</td><td>' . esc_html( (string) $batch['created_at'] ) . '</td><td>' . esc_html( (string) $batch['rows_total'] ) . '</td><td>' . esc_html( (string) $batch['rows_imported'] ) . '</td><td>' . esc_html( (string) $batch['rows_skipped'] ) . '</td><td>' . esc_html( self::batch_status_label( (string) $batch['status'] ) ) . '</td><td>';
			if ( empty( $batch['rolled_back_at'] ) && 'completed' === $batch['status'] ) {
				echo '<form method="post" action="' . esc_url( $post_url ) . '" onsubmit="return confirm(\'' . esc_js( __( 'Permanently delete all entries from this import?', 'review-captain' ) ) . '\');">';
				echo '<input type="hidden" name="action" value="review_captain_import_rollback" /><input type="hidden" name="batch_id" value="' . esc_attr( (string) $batch['id'] ) . '" />';
				wp_nonce_field( Capabilities::nonce_action( 'import_rollback' ), '_rc_nonce' );
				echo '<button type="submit" class="button button-link-delete">' . esc_html__( 'Undo import', 'review-captain' ) . '</button></form>';
			} else {
				echo esc_html( (string) ( $batch['rolled_back_at'] ?? '' ) );
			}
			echo '</td></tr>';
		}
		echo '</tbody></table></div>';
	}

	/**
	 * Render the pending dry run with confirm/cancel buttons.
	 *
	 * @param array<string, mixed> $pending  Pending data.
	 * @param string               $post_url admin-post URL.
	 */
	private function render_pending( array $pending, string $post_url ): void {
		$r = (array) $pending['result'];
		echo '<div class="rc-dry-run"><h3>' . esc_html__( 'Dry run', 'review-captain' ) . ': ' . esc_html( (string) $pending['filename'] ) . '</h3>';
		echo '<ul>';
		/* translators: %d: number */
		echo '<li>' . esc_html( sprintf( __( 'Data rows: %d', 'review-captain' ), (int) $r['rows'] ) ) . '</li>';
		/* translators: %d: number */
		echo '<li>' . esc_html( sprintf( __( 'Importable: %d', 'review-captain' ), (int) $r['valid'] ) ) . '</li>';
		/* translators: %d: number */
		echo '<li>' . esc_html( sprintf( __( 'Invalid rows: %d', 'review-captain' ), count( array_unique( array_column( (array) $r['errors'], 'line' ) ) ) ) ) . '</li>';
		/* translators: %d: number */
		echo '<li>' . esc_html( sprintf( __( 'Duplicates (will be skipped): %d', 'review-captain' ), count( (array) $r['duplicates'] ) ) ) . '</li>';
		echo '</ul>';
		echo '<p>' . esc_html__( 'For imported reviews, there is no consent to publication documented in the plugin.', 'review-captain' ) . '</p>';
		$issues = array_merge( (array) $r['errors'], (array) ( $r['warnings'] ?? array() ) );
		if ( $issues ) {
			echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Row', 'review-captain' ) . '</th><th>' . esc_html__( 'Code', 'review-captain' ) . '</th><th>' . esc_html__( 'Note', 'review-captain' ) . '</th></tr></thead><tbody>';
			foreach ( array_slice( $issues, 0, 200 ) as $issue ) {
				echo '<tr><td>' . esc_html( (string) $issue['line'] ) . '</td><td><code>' . esc_html( (string) $issue['code'] ) . '</code></td><td>' . esc_html( (string) $issue['message'] ) . '</td></tr>';
			}
			echo '</tbody></table>';
		}
		if ( $r['duplicates'] ) {
			echo '<p>' . esc_html__( 'Duplicates in row:', 'review-captain' ) . ' ' . esc_html( implode( ', ', array_map( 'intval', (array) $r['duplicates'] ) ) ) . '</p>';
		}
		echo '<form method="post" action="' . esc_url( $post_url ) . '" style="display:inline-block;margin-right:8px">';
		echo '<input type="hidden" name="action" value="review_captain_import_confirm" /><input type="hidden" name="token" value="' . esc_attr( (string) $pending['token'] ) . '" />';
		wp_nonce_field( Capabilities::nonce_action( 'import_confirm' ), '_rc_nonce' );
		echo '<button type="submit" class="button button-primary"' . ( (int) $r['valid'] > 0 ? '' : ' disabled="disabled"' ) . '>' . esc_html__( 'Confirm import', 'review-captain' ) . '</button></form>';
		echo '<form method="post" action="' . esc_url( $post_url ) . '" style="display:inline-block">';
		echo '<input type="hidden" name="action" value="review_captain_import_cancel" />';
		wp_nonce_field( Capabilities::nonce_action( 'import_cancel' ), '_rc_nonce' );
		echo '<button type="submit" class="button">' . esc_html__( 'Cancel', 'review-captain' ) . '</button></form></div>';
	}

	/**
	 * Notice from redirect args.
	 */
	private function render_notice(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display only.
		$notice = isset( $_GET['rc_notice'] ) ? sanitize_key( wp_unslash( (string) $_GET['rc_notice'] ) ) : '';
		$num    = static fn( string $k ): int => isset( $_GET[ $k ] ) ? absint( wp_unslash( $_GET[ $k ] ) ) : 0;
		// phpcs:enable
		if ( '' === $notice ) {
			return;
		}
		$type = 'success';
		if ( str_starts_with( $notice, 'upload_' ) ) {
			$text = UploadStore::message( substr( $notice, 7 ) );
			$type = 'error';
		} else {
			$text = match ( $notice ) {
				/* translators: 1: imported, 2: skipped */
				'imported'         => sprintf( __( '%1$d entries imported, %2$d skipped.', 'review-captain' ), $num( 'rc_imported' ), $num( 'rc_skipped' ) ),
				'import_failed'    => __( 'The import could not be completed.', 'review-captain' ),
				'import_expired'   => __( 'The dry run has expired. Please upload the file again.', 'review-captain' ),
				'dry_run'          => __( 'Dry run complete. Please check the result and confirm the import.', 'review-captain' ),
				'cancelled'        => __( 'Import canceled, file deleted.', 'review-captain' ),
				/* translators: %d: number */
				'rolled_back'      => sprintf( __( '%d imported entries were deleted.', 'review-captain' ), $num( 'rc_deleted' ) ),
				/* translators: %d: number */
				'rollback_confirm' => sprintf( __( '%d entries from this import have since been moderated or edited. Please confirm to delete them.', 'review-captain' ), $num( 'rc_modified' ) ),
				default            => '',
			};
			if ( in_array( $notice, array( 'import_failed', 'import_expired' ), true ) ) {
				$type = 'error';
			} elseif ( 'rollback_confirm' === $notice ) {
				$type = 'warning';
			}
		}
		if ( '' === $text ) {
			return;
		}
		echo '<div class="notice notice-' . esc_attr( $type ) . '"><p>' . esc_html( $text ) . '</p>';
		if ( 'rollback_confirm' === $notice ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="review_captain_import_rollback" /><input type="hidden" name="batch_id" value="' . esc_attr( (string) $num( 'rc_batch' ) ) . '" /><input type="hidden" name="confirm_modified" value="1" />';
			wp_nonce_field( Capabilities::nonce_action( 'import_rollback' ), '_rc_nonce' );
			echo '<p><button type="submit" class="button button-link-delete">' . esc_html__( 'Undo anyway', 'review-captain' ) . '</button></p></form>';
		}
		echo '</div>';
	}

	/**
	 * Select row for the export form.
	 *
	 * @param string                $name    Field.
	 * @param string                $label   Label.
	 * @param array<string, string> $choices Choices.
	 */
	private function select_row( string $name, string $label, array $choices ): void {
		echo '<tr><th scope="row"><label for="rc-export-' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label></th><td><select id="rc-export-' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '"><option value="">' . esc_html__( 'All', 'review-captain' ) . '</option>';
		foreach ( $choices as $value => $text ) {
			echo '<option value="' . esc_attr( $value ) . '">' . esc_html( $text ) . '</option>';
		}
		echo '</select></td></tr>';
	}

	/**
	 * Batch status label.
	 *
	 * @param string $status Status.
	 */
	private static function batch_status_label( string $status ): string {
		return match ( $status ) {
			'completed'   => __( 'completed', 'review-captain' ),
			'running'     => __( 'running', 'review-captain' ),
			'rolled_back' => __( 'undone', 'review-captain' ),
			'failed'      => __( 'failed', 'review-captain' ),
			default       => $status,
		};
	}
}
