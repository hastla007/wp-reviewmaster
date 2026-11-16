<?php
/**
 * CSV export (RC-044) and import with dry run and rollback (RC-045).
 *
 * @package ReviewCaptain
 */

namespace ReviewCaptain\Admin;

use ReviewCaptain\ImportExport\Csv;

defined( 'ABSPATH' ) || exit;

final class ImportExportPage {

	private const PAGE = 'review-captain-import';

	public static function register_actions(): void {
		add_action( 'admin_post_review_captain_export', array( self::class, 'handle_export' ) );
		add_action( 'admin_post_review_captain_import_preview', array( self::class, 'handle_preview' ) );
		add_action( 'admin_post_review_captain_import_run', array( self::class, 'handle_import' ) );
		add_action( 'admin_post_review_captain_import_rollback', array( self::class, 'handle_rollback' ) );
	}

	private static function preview_key(): string {
		return 'rc_import_preview_' . get_current_user_id();
	}

	public static function render(): void {
		echo '<div class="wrap"><h1>' . esc_html__( 'Import & Export', 'review-captain' ) . '</h1>';
		Menu::notices();

		// Export.
		echo '<div class="postbox"><div class="inside"><h2>' . esc_html__( 'Export reviews (CSV)', 'review-captain' ) . '</h2>';
		echo '<p>' . esc_html__( 'UTF-8 CSV with all source fields. Cells that spreadsheet programs could execute as formulas are neutralized.', 'review-captain' ) . '</p>';
		echo '<form method="post" action="' . esc_url( Menu::post_url() ) . '"><input type="hidden" name="action" value="review_captain_export">';
		wp_nonce_field( 'rc_export' );
		echo '<label>' . esc_html__( 'Status', 'review-captain' ) . ' <select name="status"><option value="">' . esc_html__( 'All', 'review-captain' ) . '</option>';
		foreach ( ReviewsListTable::status_labels() as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '">' . esc_html( $l ) . '</option>';
		}
		echo '</select></label> ';
		echo '<label>' . esc_html__( 'From', 'review-captain' ) . ' <input type="date" name="date_from"></label> <label>' . esc_html__( 'To', 'review-captain' ) . ' <input type="date" name="date_to"></label> ';
		submit_button( __( 'Download CSV', 'review-captain' ), 'secondary', 'submit', false );
		echo '</form></div></div>';

		// Import preview result.
		$preview = get_transient( self::preview_key() );
		if ( is_array( $preview ) ) {
			echo '<div class="postbox"><div class="inside"><h2>' . esc_html__( 'Check result', 'review-captain' ) . '</h2>';
			/* translators: 1: valid rows, 2: errors, 3: duplicates */
			echo '<p>' . esc_html( sprintf( __( '%1$d rows can be imported, %2$d rows have errors, %3$d rows look like duplicates.', 'review-captain' ), count( $preview['valid'] ), count( $preview['errors'] ), count( $preview['duplicates'] ) ) ) . '</p>';
			if ( $preview['errors'] || $preview['duplicates'] ) {
				echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Line', 'review-captain' ) . '</th><th>' . esc_html__( 'Problem', 'review-captain' ) . '</th></tr></thead><tbody>';
				$all = $preview['errors'] + $preview['duplicates'];
				ksort( $all );
				foreach ( array_slice( $all, 0, 200, true ) as $line => $msg ) {
					echo '<tr><td>' . (int) $line . '</td><td>' . esc_html( $msg ) . '</td></tr>';
				}
				echo '</tbody></table>';
			}
			if ( $preview['valid'] ) {
				echo '<form method="post" action="' . esc_url( Menu::post_url() ) . '"><input type="hidden" name="action" value="review_captain_import_run">';
				wp_nonce_field( 'rc_import_run' );
				echo '<p class="description">' . esc_html__( 'Imported reviews stay pending unless the file sets the status “approved”. Imported reviews are never marked as verified.', 'review-captain' ) . '</p>';
				submit_button( __( 'Import now', 'review-captain' ), 'primary', 'submit', false );
				echo '</form>';
			}
			echo '</div></div>';
		}

		// Import upload.
		echo '<div class="postbox"><div class="inside"><h2>' . esc_html__( 'Import reviews (CSV)', 'review-captain' ) . '</h2>';
		echo '<p>' . esc_html__( 'Columns:', 'review-captain' ) . ' <code>' . esc_html( implode( ', ', Csv::IMPORT_COLUMNS ) ) . '</code>. ' . esc_html__( 'provider is “site” for reviews collected on your website, otherwise google, trustpilot, etrusted, apple_app_store, google_play or other. A check run shows errors and duplicates before anything is saved.', 'review-captain' ) . '</p>';
		echo '<p><a href="' . esc_url( 'https://github.com/hastla007/wp-reviewmaster/blob/main/docs/migration.md' ) . '" rel="noopener">' . esc_html__( 'Migration guide (WooCommerce and other review plugins)', 'review-captain' ) . '</a></p>';
		echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( Menu::post_url() ) . '"><input type="hidden" name="action" value="review_captain_import_preview">';
		wp_nonce_field( 'rc_import_preview' );
		echo '<input type="file" name="csv" accept=".csv,text/csv" required> ';
		submit_button( __( 'Check file', 'review-captain' ), 'secondary', 'submit', false );
		echo '</form></div></div>';

		// Batches.
		$batches = Csv::batches();
		if ( $batches ) {
			echo '<h2>' . esc_html__( 'Recent imports', 'review-captain' ) . '</h2><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Date', 'review-captain' ) . '</th><th>' . esc_html__( 'File', 'review-captain' ) . '</th><th>' . esc_html__( 'Imported', 'review-captain' ) . '</th><th>' . esc_html__( 'Status', 'review-captain' ) . '</th><th></th></tr></thead><tbody>';
			foreach ( $batches as $b ) {
				echo '<tr><td>' . esc_html( $b['created_at'] ) . ' UTC</td><td>' . esc_html( (string) $b['filename'] ) . '</td><td><a href="' . esc_url( admin_url( 'admin.php?page=review-captain-reviews&batch=' . (int) $b['id'] ) ) . '">' . (int) $b['rows_imported'] . '</a></td><td>' . esc_html( $b['status'] ) . '</td><td>';
				if ( 'imported' === $b['status'] ) {
					echo '<form method="post" action="' . esc_url( Menu::post_url() ) . '" onsubmit="return confirm(\'' . esc_js( __( 'Remove all reviews of this import?', 'review-captain' ) ) . '\');"><input type="hidden" name="action" value="review_captain_import_rollback"><input type="hidden" name="batch" value="' . (int) $b['id'] . '">';
					wp_nonce_field( 'rc_import_rollback_' . (int) $b['id'] );
					submit_button( __( 'Undo import', 'review-captain' ), 'link-delete', 'submit', false );
					echo '</form>';
				}
				echo '</td></tr>';
			}
			echo '</tbody></table>';
		}
		echo '</div>';
	}

	public static function handle_export(): void {
		review_captain_check_request( 'rc_manage_reviews', 'rc_export' );
		$filters = array();
		$status  = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';
		if ( $status ) {
			$filters['status'] = $status;
		}
		foreach ( array( 'date_from', 'date_to' ) as $d ) {
			if ( ! empty( $_POST[ $d ] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', sanitize_text_field( wp_unslash( $_POST[ $d ] ) ) ) ) {
				$filters[ $d ] = sanitize_text_field( wp_unslash( $_POST[ $d ] ) );
			}
		}
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=review-captain-' . gmdate( 'Y-m-d' ) . '.csv' );
		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		Csv::export( $out, $filters );
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	public static function handle_preview(): void {
		review_captain_check_request( 'rc_manage_reviews', 'rc_import_preview' );
		if ( empty( $_FILES['csv']['tmp_name'] ) || ! is_uploaded_file( $_FILES['csv']['tmp_name'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			Menu::back( self::PAGE, '', __( 'Please choose a CSV file.', 'review-captain' ) );
		}
		$name  = sanitize_file_name( wp_unslash( $_FILES['csv']['name'] ?? 'import.csv' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$check = wp_check_filetype( $name, array( 'csv' => 'text/csv' ) );
		if ( 'csv' !== $check['ext'] || (int) $_FILES['csv']['size'] > 10 * MB_IN_BYTES ) {
			Menu::back( self::PAGE, '', __( 'Only CSV files up to 10 MB can be imported.', 'review-captain' ) );
		}
		$parsed             = Csv::parse( $_FILES['csv']['tmp_name'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$result             = Csv::dry_run( $parsed['rows'] );
		$result['errors']  += $parsed['errors'];
		$result['filename'] = $name;
		set_transient( self::preview_key(), $result, HOUR_IN_SECONDS );
		Menu::back( self::PAGE );
	}

	public static function handle_import(): void {
		review_captain_check_request( 'rc_manage_reviews', 'rc_import_run' );
		$preview = get_transient( self::preview_key() );
		delete_transient( self::preview_key() );
		if ( ! is_array( $preview ) || empty( $preview['valid'] ) ) {
			Menu::back( self::PAGE, '', __( 'The check result expired. Please upload the file again.', 'review-captain' ) );
		}
		// Re-check right before writing (duplicates may have appeared meanwhile).
		$recheck = Csv::dry_run( $preview['valid'] );
		Csv::import( $recheck['valid'], (string) $preview['filename'] );
		Menu::back( self::PAGE, 'imported' );
	}

	public static function handle_rollback(): void {
		$batch = isset( $_POST['batch'] ) ? absint( $_POST['batch'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked below.
		review_captain_check_request( 'rc_manage_reviews', 'rc_import_rollback_' . $batch );
		Csv::rollback( $batch );
		Menu::back( self::PAGE, 'rolled_back' );
	}
}
