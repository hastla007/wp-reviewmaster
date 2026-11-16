<?php
declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\M7;

use ReviewCaptain\Frontend\FormHandler;
use ReviewCaptain\Install\Schema;
use ReviewCaptain\Media\PhotoStore;
use ReviewCaptain\Plugin;
use ReviewCaptain\Tests\Unit\Frontend\FrontendTestCase;

/**
 * Helpers for the post-MVP features (all off by default).
 */
abstract class M7TestCase extends FrontendTestCase {

	protected function setUp(): void {
		parent::setUp();
		update_option( Schema::OPTION_VERSION, Schema::VERSION );
		PhotoStore::flush();
	}

	/**
	 * Switch settings on (merged into the stored values).
	 *
	 * @param array<string, array<string, mixed>> $values Values by tab.
	 */
	protected function enable( array $values ): void {
		$settings = Plugin::instance()->settings();
		$all      = $settings->all();
		foreach ( $values as $tab => $fields ) {
			foreach ( $fields as $key => $value ) {
				$all[ $tab ][ $key ] = $value;
			}
		}
		$settings->save( $all );
	}

	/**
	 * Valid review form POST.
	 *
	 * @param array<string, mixed> $o    Overrides.
	 * @param string               $type Context type.
	 * @param string               $cid  Context ID.
	 * @return array<string, mixed>
	 */
	protected function form_post( array $o = array(), string $type = '', string $cid = '' ): array {
		return array_merge(
			array(
				'_rc_nonce'       => wp_create_nonce( FormHandler::NONCE_ACTION ),
				'rc_form'         => '1',
				'rc_ts'           => FormHandler::sign_timestamp( time() - 10 ),
				'rc_context_type' => $type,
				'rc_context_id'   => $cid,
				'rc_context_sig'  => FormHandler::sign_context( $type, $cid ),
				'rc_return'       => 'https://example.test/produkt/',
				'rc_rating'       => '4',
				'rc_body'         => 'Die Beratung war ausführlich und freundlich, gerne wieder.',
				'rc_name'         => 'Carla',
				'rc_email'        => 'carla@example.test',
				'rc_consent'      => '1',
				'rc_website'      => '',
			),
			$o
		);
	}

	protected function form(): FormHandler {
		/** @var FormHandler $handler */
		$handler = Plugin::instance()->get( 'form_handler' );
		$handler->set_require_upload( false );
		return $handler;
	}
}
