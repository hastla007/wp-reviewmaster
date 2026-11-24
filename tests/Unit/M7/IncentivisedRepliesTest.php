<?php
declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\M7;

use ReviewCaptain\Admin\ReviewEditor;
use ReviewCaptain\Admin\SettingsFields;
use ReviewCaptain\Domain\Platform;
use ReviewCaptain\Domain\Review;
use ReviewCaptain\Frontend\PublicTexts;
use ReviewCaptain\ImportExport\Csv;
use ReviewCaptain\ImportExport\CsvExporter;
use ReviewCaptain\ImportExport\CsvImporter;
use ReviewCaptain\Install\Migrator;
use ReviewCaptain\Install\Schema;
use ReviewCaptain\Plugin;
use ReviewCaptain\Privacy\PersonalData;
use ReviewCaptain\Seo\SchemaEligibility;
use ReviewCaptain\Seo\SeoEnvironment;
use ReviewCaptain\Settings\Settings;
use ReviewCaptain\Settings\SettingsSchema;
use ReviewCaptain\Widgets\WidgetDefinition;

/**
 * SET-RC-12 (E7) flag "received in exchange for a benefit" and SET-RC-13 (E4) merchant reply:
 * schema v5, settings, card output, structured data, CSV, privacy export/eraser, editor, API.
 */
final class IncentivisedRepliesTest extends M7TestCase {

	/** @var list<string> */
	private array $files = array();

	protected function tearDown(): void {
		foreach ( $this->files as $f ) {
			if ( is_file( $f ) ) {
				unlink( $f );
			}
		}
		parent::tearDown();
	}

	// ---------------------------------------------------------------- schema v5

	public function test_schema_v5_columns_and_rerunnable_migration_step(): void {
		$this->assertGreaterThanOrEqual( 5, Schema::VERSION );
		$def = Schema::definitions()['reviews'];
		$this->assertStringContainsString( 'incentivised tinyint(1) NOT NULL DEFAULT 0', $def );
		$this->assertStringContainsString( 'reply_body text NULL', $def );
		$this->assertStringContainsString( 'reply_at datetime NULL', $def );
		$this->assertArrayHasKey( 5, Migrator::steps() );

		$t = Schema::table( 'reviews' );
		$this->db->insert( $t, array( 'uuid' => wp_generate_uuid4(), 'body' => 'alt', 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00' ) );
		Migrator::run( 4, 5 );
		Migrator::run( 4, 5 );
		$this->assertSame( '0', (string) $this->db->get_var( "SELECT incentivised FROM {$t}" ) );
		$this->assertNull( $this->db->get_var( "SELECT reply_body FROM {$t}" ) );

		// A row read from an older schema (no such keys) gets safe defaults.
		$old = Review::from_row( array( 'uuid' => 'x', 'body' => 'y' ) );
		$this->assertFalse( $old->incentivised );
		$this->assertNull( $old->reply_body );
		$this->assertNull( $old->reply_at );
	}

	public function test_round_trip_and_reply_only_for_own_reviews(): void {
		$r = $this->review( array( 'incentivised' => true, 'reply_body' => 'Danke!', 'reply_at' => '2026-10-02 08:00:00' ) );
		$back = Plugin::instance()->reviews()->find( (int) $r->id );
		$this->assertTrue( $back->incentivised );
		$this->assertSame( 'Danke!', $back->reply_body );
		$this->assertSame( '2026-10-02 08:00:00', $back->reply_at );

		$ext             = new Review();
		$ext->platform   = Platform::GOOGLE;
		$ext->source_url = 'https://www.google.com/maps/review/1';
		$ext->body       = 'x';
		$ext->reply_body = 'Antwort';
		$this->assertContains( 'reply_on_external', $ext->validate() );
	}

	public function test_sync_keeps_moderator_flag_and_reply(): void {
		$repo = Plugin::instance()->reviews();
		$r    = $this->review( array( 'platform' => Platform::GOOGLE, 'external_id' => 'g-1' ) );
		$r->incentivised = true;
		$repo->update( $r );

		$incoming              = new Review();
		$incoming->platform    = Platform::GOOGLE;
		$incoming->external_id = 'g-1';
		$incoming->source_url  = 'https://www.google.com/maps/review/abc';
		$incoming->body        = 'Neu synchronisiert';
		$incoming->rating_value = 4.0;
		$repo->upsert_external( $incoming );
		$this->assertTrue( $repo->find( (int) $r->id )->incentivised, 'a source never removes the flag' );
	}

	// ---------------------------------------------------------------- settings

	public function test_settings_defaults_validation_and_reset(): void {
		$s = new Settings();
		$this->assertSame( '', $s->get( 'display', 'label_incentivised' ) );
		$this->assertTrue( $s->get( 'display', 'show_replies' ) );
		$this->assertSame( '', $s->get( 'display', 'reply_label' ) );

		$out = $s->sanitize(
			array(
				'display' => array(
					'label_incentivised' => str_repeat( 'x', 200 ) . '<b>',
					'show_replies'       => '0',
					'reply_label'        => '<script>x</script>Antwort von {brand}',
				),
			)
		);
		$this->assertSame( 60, mb_strlen( $out['display']['label_incentivised'] ) );
		$this->assertFalse( $out['display']['show_replies'] );
		$this->assertStringNotContainsString( '<script>', $out['display']['reply_label'] );

		$this->enable( array( 'display' => array( 'label_incentivised' => 'Mit Gutschein belohnt', 'show_replies' => false, 'reply_label' => 'Team' ) ) );
		$settings = Plugin::instance()->settings();
		$settings->reset( array( 'display' ) );
		$settings->flush();
		$this->assertSame( '', $settings->get( 'display', 'label_incentivised' ) );
		$this->assertTrue( $settings->get( 'display', 'show_replies' ) );
		$this->assertSame( '', $settings->get( 'display', 'reply_label' ) );

		$sections = array_merge( ...array_values( SettingsFields::sections()['display'] ) );
		foreach ( array( 'label_incentivised', 'show_replies', 'reply_label' ) as $key ) {
			$this->assertArrayHasKey( $key, SettingsSchema::fields()['display'] );
			$this->assertNotSame( $key, SettingsFields::label( 'display', $key ), $key );
			$this->assertNotSame( '', SettingsFields::description( 'display', $key ), $key );
			$this->assertContains( $key, $sections, $key );
		}
	}

	public function test_settings_export_and_import_carry_the_new_values(): void {
		$this->enable( array( 'display' => array( 'label_incentivised' => 'Mit Gutschein belohnt', 'show_replies' => false, 'reply_label' => 'Antwort des Teams' ) ) );
		$transfer = new \ReviewCaptain\Admin\ConfigTransfer( new Settings() );
		$doc      = $transfer->export();
		$this->assertSame( 'Mit Gutschein belohnt', $doc['settings']['display']['label_incentivised'] );
		$this->assertFalse( $doc['settings']['display']['show_replies'] );

		Plugin::instance()->settings()->reset( array( 'display' ) );
		$preview = ( new \ReviewCaptain\Admin\ConfigTransfer( new Settings() ) )->preview( (string) wp_json_encode( $doc ) );
		$diff    = array();
		foreach ( $preview['diff'] as $row ) {
			$diff[ $row['tab'] . '.' . $row['key'] ] = $row['new'];
		}
		$this->assertSame( 'Mit Gutschein belohnt', $diff['display.label_incentivised'] );
		$this->assertSame( 'Antwort des Teams', $diff['display.reply_label'] );
		$this->assertFalse( $diff['display.show_replies'] );
	}

	// ---------------------------------------------------------------- SET-RC-12 card output

	public function test_flag_label_default_custom_and_not_hideable(): void {
		$this->review( array( 'incentivised' => true, 'author_name' => 'Belohnt' ) );
		$this->review( array( 'author_name' => 'Ohne' ) );
		$html = $this->shortcode( array( 'v' => '1' ) );
		$this->assertSame( 1, substr_count( $html, 'rc-badge--incentivised' ) );
		$this->assertStringContainsString( 'Für diese Bewertung wurde ein Vorteil gewährt', $html );

		// Custom text.
		$this->enable( array( 'display' => array( 'label_incentivised' => 'Mit Gutschein belohnt' ) ) );
		$this->renderer()->reset_instances();
		$this->assertStringContainsString( 'Mit Gutschein belohnt', $this->shortcode( array( 'v' => '1' ) ) );

		// A filter returning '' cannot hide it: translated default.
		add_filter( 'review_captain_public_text', static fn( $text, $id ) => 'display.label_incentivised' === $id ? '' : $text, 10, 2 );
		$this->renderer()->reset_instances();
		$html = $this->shortcode( array( 'v' => '1' ) );
		$this->assertStringContainsString( 'Für diese Bewertung wurde ein Vorteil gewährt', $html );

		// Other display switches do not hide it either.
		$this->enable( array( 'display' => array( 'show_author' => false, 'show_date' => false, 'show_stars' => false, 'show_unverified_label' => false ) ) );
		$this->renderer()->reset_instances();
		$this->assertSame( 1, substr_count( $this->shortcode( array( 'v' => '1' ) ), 'rc-badge--incentivised' ) );
	}

	public function test_theme_template_without_badges_still_shows_the_label(): void {
		$dir  = sys_get_temp_dir() . '/rc-theme-' . wp_generate_password( 6, false );
		mkdir( $dir );
		$file          = $dir . '/review-card.php';
		$this->files[] = $file;
		file_put_contents( $file, '<article class="rc-card"><p><?php echo esc_html( $rc[\'body_html\'] ? \'Text\' : \'\' ); ?></p></article>' );
		add_filter( 'review_captain_template', static fn( $path, $name ) => 'review-card.php' === $name ? $file : $path, 10, 2 );

		$this->review( array( 'incentivised' => true ) );
		$html = $this->shortcode( array( 'v' => '1' ) );
		$this->assertMatchesRegularExpression( '#<article class="rc-card"><p class="rc-badge rc-badge--incentivised">Für diese Bewertung wurde ein Vorteil gewährt</p>#', $html );
		unlink( $file );
		rmdir( $dir );
	}

	public function test_incentivised_reviews_never_get_structured_data(): void {
		$r               = new Review();
		$r->platform     = Platform::LOCAL;
		$r->status       = \ReviewCaptain\Domain\ReviewStatus::APPROVED;
		$r->context_type = 'product';
		$r->context_id   = '10';
		$r->body         = 'Text';
		$ctx             = array(
			'rendered'    => true,
			'environment' => SeoEnvironment::from_state( array() ),
		);
		$this->assertTrue( SchemaEligibility::is_eligible( $r, $ctx ) );
		$r->incentivised = true;
		$this->assertSame( array( 'eligible' => false, 'reason' => 'incentivised' ), SchemaEligibility::evaluate( $r, $ctx ) );
		$this->assertContains( 'incentivised', SchemaEligibility::REASONS );
		// The filter cannot unlock it (E8 only tightens).
		add_filter( SchemaEligibility::FILTER, static fn() => true );
		$this->assertFalse( SchemaEligibility::is_eligible( $r, $ctx ) );
		$this->assertNotSame( 'incentivised', SchemaEligibility::reason_label( 'incentivised' ) );
	}

	// ---------------------------------------------------------------- SET-RC-13 card output

	public function test_reply_rendering_label_brand_and_switch(): void {
		update_option( 'blogname', 'Muster GmbH' );
		$this->review( array( 'reply_body' => "Danke für das Lob!\n\n<b>Bis bald</b>", 'reply_at' => '2026-10-05 09:00:00' ) );
		$html = $this->shortcode( array( 'v' => '1' ) );
		$this->assertStringContainsString( '<section class="rc-reply"', $html );
		$this->assertStringContainsString( 'Antwort von Muster GmbH', $html );
		$this->assertStringContainsString( 'datetime="2026-10-05T09:00:00+00:00"', $html );
		$this->assertStringContainsString( '&lt;b&gt;Bis bald&lt;/b&gt;', $html, 'reply is escaped' );
		$this->assertStringNotContainsString( '<b>Bis bald', $html );

		$this->enable( array( 'general' => array( 'brand_name' => 'Muster & Co' ), 'display' => array( 'reply_label' => 'Das {brand}-Team antwortet' ) ) );
		$this->assertSame( 'Das Muster & Co-Team antwortet', PublicTexts::reply_label( Plugin::instance()->settings() ) );
		$this->renderer()->reset_instances();
		$this->assertStringContainsString( 'Das Muster &amp; Co-Team antwortet', $this->shortcode( array( 'v' => '1' ) ) );

		$this->enable( array( 'display' => array( 'show_replies' => false ) ) );
		$this->renderer()->reset_instances();
		$this->assertStringNotContainsString( 'rc-reply', $this->shortcode( array( 'v' => '1' ) ) );
	}

	public function test_reply_never_changes_the_average(): void {
		$a = $this->review( array( 'rating_value' => 5.0 ) );
		$this->review( array( 'rating_value' => 2.0 ) );
		$d      = WidgetDefinition::from_atts( array( 'v' => '1' ) );
		$before = $this->renderer()->summary( $d );
		$a->reply_body = 'Vielen Dank';
		$a->reply_at   = '2026-10-03 00:00:00';
		Plugin::instance()->reviews()->update( $a );
		$this->assertSame( $before, $this->renderer()->summary( $d ) );
	}

	// ---------------------------------------------------------------- editor

	public function test_editor_sets_flag_and_reply_with_date(): void {
		$r = $this->review();
		[ $fields, $errors ] = ReviewEditor::apply_edit( $r, array( 'incentivised' => '1', 'reply_body' => '  Danke!  ', 'reply_at' => '' ) );
		$this->assertSame( array(), $errors );
		$this->assertContains( 'incentivised', $fields );
		$this->assertContains( 'reply_body', $fields );
		$this->assertTrue( $r->incentivised );
		$this->assertSame( 'Danke!', $r->reply_body );
		$this->assertNotNull( $r->reply_at );

		[ , $errors ] = ReviewEditor::apply_edit( $r, array( 'reply_body' => 'Danke!', 'reply_at' => '2026-10-07' ) );
		$this->assertSame( array(), $errors );
		$this->assertSame( '2026-10-07 00:00:00', $r->reply_at );

		[ $fields ] = ReviewEditor::apply_edit( $r, array( 'incentivised' => '0', 'reply_body' => '' ) );
		$this->assertFalse( $r->incentivised );
		$this->assertNull( $r->reply_body );
		$this->assertNull( $r->reply_at );
		$this->assertContains( 'reply_at', $fields );

		[ , $errors ] = ReviewEditor::apply_edit( $r, array( 'reply_body' => str_repeat( 'a', ReviewEditor::REPLY_MAX_LENGTH + 1 ) ) );
		$this->assertContains( 'reply_too_long', $errors );

		// Without the keys (e.g. another form), nothing changes.
		$r2 = $this->review( array( 'incentivised' => true ) );
		ReviewEditor::apply_edit( $r2, array( 'title' => 'Neu' ) );
		$this->assertTrue( $r2->incentivised );

		// External reviews: flag yes, reply no (platform replies are not part of the core).
		$ext = $this->review( array( 'platform' => Platform::GOOGLE ) );
		[ $fields ] = ReviewEditor::apply_edit( $ext, array( 'incentivised' => '1', 'reply_body' => 'Antwort' ) );
		$this->assertTrue( $ext->incentivised );
		$this->assertNull( $ext->reply_body );
		$this->assertSame( array( 'incentivised' ), $fields );
	}

	// ---------------------------------------------------------------- CSV

	public function test_csv_export_and_import_of_flag_and_reply(): void {
		$this->review( array( 'incentivised' => true, 'reply_body' => 'Danke', 'reply_at' => '2026-10-02 08:00:00' ) );
		$h = fopen( 'php://memory', 'w+b' );
		( new CsvExporter( Plugin::instance()->reviews() ) )->write( $h, array() );
		rewind( $h );
		$raw = substr( (string) stream_get_contents( $h ), 3 );
		fclose( $h );
		$lines = array_map( static fn( $l ) => str_getcsv( $l, ',', '"', '' ), array_values( array_filter( explode( "\n", $raw ) ) ) );
		$row   = array_combine( $lines[0], $lines[1] );
		$this->assertSame( '1', $row['incentivised'] );
		$this->assertSame( 'Danke', $row['reply_body'] );
		$this->assertSame( '2026-10-02T08:00:00Z', $row['reply_at'] );
		$this->assertContains( 'incentivised', Csv::IMPORT_COLUMNS );
		$this->assertNotContains( 'reply_body', Csv::IMPORT_COLUMNS );

		$header        = 'kind,platform,external_id,rating_value,rating_scale_max,title,body,language,author_name,published_at,source_url,verification,status,context_type,context_id,incentivised';
		$path          = (string) tempnam( sys_get_temp_dir(), 'rc-csv' );
		$this->files[] = $path;
		file_put_contents(
			$path,
			$header . "\n"
			. "review,local,,5,5,,Mit Gutschein,de,Ina,2026-01-01,,unverified,approved,,,ja\n"
			. "review,local,,4,5,,Ohne,de,Olaf,2026-01-02,,unverified,approved,,,\n"
			. "review,local,,3,5,,Kaputt,de,Kai,2026-01-03,,unverified,approved,,,vielleicht\n"
		);
		$result = ( new CsvImporter( Plugin::instance()->reviews() ) )->dry_run( $path );
		$this->assertSame( 2, $result['valid'] );
		$this->assertSame( array( 'invalid_flag' ), array_column( array_filter( $result['errors'], static fn( $e ) => 4 === $e['line'] ), 'code' ) );
		( new CsvImporter( Plugin::instance()->reviews() ) )->import( $path, 1 );
		$flags = array();
		foreach ( (array) $this->db->get_results( 'SELECT author_name, incentivised FROM ' . Schema::table( 'reviews' ) . " WHERE origin = 'csv_import'", ARRAY_A ) as $imported ) {
			$flags[ $imported['author_name'] ] = (string) $imported['incentivised'];
		}
		ksort( $flags );
		$this->assertSame( array( 'Ina' => '1', 'Olaf' => '0' ), $flags );
	}

	// ---------------------------------------------------------------- privacy

	public function test_privacy_export_lists_flag_and_reply_and_eraser_removes_reply(): void {
		$this->review( array( 'author_email' => 'ina@example.test', 'incentivised' => true, 'reply_body' => 'Danke Ina!', 'reply_at' => '2026-10-02 08:00:00' ) );
		$service = new PersonalData( Plugin::instance()->reviews(), Plugin::instance()->settings() );
		$values  = array_column( $service->export( 'ina@example.test' )['data'][0]['data'], 'value', 'name' );
		$this->assertSame( 'ja', $values['Gegen einen Vorteil erhalten'] );
		$this->assertSame( 'Danke Ina!', $values['Antwort des Händlers'] );
		$this->assertSame( '2026-10-02 08:00:00', $values['Antwort am (UTC)'] );

		$service->erase( 'ina@example.test' );
		$row = $this->db->get_row( 'SELECT reply_body, reply_at, incentivised FROM ' . Schema::table( 'reviews' ), ARRAY_A );
		$this->assertNull( $row['reply_body'] );
		$this->assertNull( $row['reply_at'] );
		$this->assertSame( '1', (string) $row['incentivised'], 'the flag is not personal data and stays' );
	}

	public function test_tombstone_scrub_removes_reply(): void {
		$repo = Plugin::instance()->reviews();
		$r    = $this->review( array( 'reply_body' => 'Danke', 'reply_at' => '2026-10-02 08:00:00' ) );
		$this->db->query( $this->db->prepare( 'UPDATE ' . Schema::table( 'reviews' ) . " SET status = 'removed' WHERE id = %d", $r->id ) );
		$this->assertTrue( $repo->scrub_removed( (int) $r->id, 'remote_removed' ) );
		$this->assertNull( $repo->find( (int) $r->id )->reply_body );
	}

	// ---------------------------------------------------------------- API / campaigns

	public function test_workflow_draft_and_submission_filter_set_the_flag(): void {
		$wf   = Plugin::instance()->workflow();
		$base = array(
			'contract'        => '1.0',
			'rating'          => 5,
			'review_text'     => 'Der Kurs war gut strukturiert und praxisnah.',
			'display_name'    => 'Lena',
		);
		$ok = $wf->create_draft( $base + array( 'idempotency_key' => 'voucher:1', 'incentivised' => true ) );
		$this->assertTrue( $ok['success'] );
		$this->assertTrue( Plugin::instance()->reviews()->find( (int) $ok['data']['review_id'] )->incentivised );

		$plain = $wf->create_draft( $base + array( 'idempotency_key' => 'voucher:2' ) );
		$this->assertFalse( Plugin::instance()->reviews()->find( (int) $plain['data']['review_id'] )->incentivised );

		$bad = $wf->create_draft( $base + array( 'idempotency_key' => 'voucher:3', 'incentivised' => 'yes' ) );
		$this->assertFalse( $bad['success'] );
		$this->assertSame( 'incentivised', $bad['data']['field'] );

		add_filter( 'review_captain_submission_incentivised', static fn() => true );
		$this->form()->process( $this->form_post() );
		$stored = $this->db->get_var( 'SELECT incentivised FROM ' . Schema::table( 'reviews' ) . " WHERE author_name = 'Carla'" );
		$this->assertSame( '1', (string) $stored );
	}
}
