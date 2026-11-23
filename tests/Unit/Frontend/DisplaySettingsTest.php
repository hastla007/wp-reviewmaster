<?php
/**
 * SET-RC-22 rating display and colours, SET-RC-28 global widget defaults.
 */

declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\Frontend;

use ReviewCaptain\Domain\Platform;
use ReviewCaptain\Domain\Review;
use ReviewCaptain\Frontend\FrontendModule;
use ReviewCaptain\Settings\SettingsSchema;
use ReviewCaptain\Widgets\WidgetDefinition;

final class DisplaySettingsTest extends FrontendTestCase {

	private function rated( float $value, float $max = 5.0 ): Review {
		$r                   = new Review();
		$r->rating_value     = $value;
		$r->rating_scale_max = $max;
		return $r;
	}

	public function test_rating_display_modes(): void {
		$both = $this->renderer()->rating_data( $this->rated( 4 ) );
		$this->assertCount( 5, $both['stars'] );
		$this->assertTrue( $both['show_value'], 'default "both" = behaviour up to now' );

		$stars = $this->renderer()->rating_data( $this->rated( 4 ), true, 'stars' );
		$this->assertCount( 5, $stars['stars'] );
		$this->assertFalse( $stars['show_value'] );
		$this->assertSame( '4 out of 5 stars', $stars['label'], 'screen reader label always present' );

		$number = $this->renderer()->rating_data( $this->rated( 4 ), true, 'number' );
		$this->assertSame( array(), $number['stars'] );
		$this->assertTrue( $number['show_value'] );

		$ten = $this->renderer()->rating_data( $this->rated( 8, 10 ), true, 'stars' );
		$this->assertSame( array(), $ten['stars'], 'other scales are never turned into stars' );
		$this->assertTrue( $ten['show_value'], 'without stars the number is always shown' );
	}

	public function test_card_hides_number_in_stars_mode(): void {
		$this->review();
		$this->settings( array( 'display' => array( 'rating_display' => 'stars' ) ) );
		$html = $this->shortcode();
		$this->assertStringContainsString( 'rc-stars', $html );
		$this->assertStringNotContainsString( 'rc-rating__value', $html );
		$this->assertStringContainsString( 'aria-label="5 out of 5 stars"', $html );
	}

	public function test_decimal_places_for_averages(): void {
		$this->assertSame( '4,3', $this->renderer()->format_average( 4.333 ), 'default 1' );
		$this->settings( array( 'display' => array( 'decimal_places' => 2 ) ) );
		$this->assertSame( '4,33', $this->renderer()->format_average( 4.333 ) );
		$this->settings( array( 'display' => array( 'decimal_places' => 0 ) ) );
		$this->assertSame( '4', $this->renderer()->format_average( 4.333 ) );
		$this->assertSame( '5', $this->renderer()->format_average( 5.0 ) );

		$this->review( array( 'rating_value' => 4.0 ) );
		$this->review( array( 'rating_value' => 5.0 ) );
		$this->review( array( 'rating_value' => 5.0 ) );
		$this->settings( array( 'display' => array( 'decimal_places' => 2 ) ) );
		$summary = $this->renderer()->summary( WidgetDefinition::from_atts( array() ) );
		$this->assertStringContainsString( '4,67 out of 5', $summary[0]['text'] );
	}

	public function test_colours_become_css_custom_properties(): void {
		$this->assertSame( '', FrontendModule::inline_css(), 'empty = theme/CSS default, nothing added' );
		$this->settings( array( 'display' => array( 'star_color' => '#E0A800', 'accent_color' => '#123' ) ) );
		$this->assertSame( '.rc-widget,.rc-form,.rc-cta{--rc-color-star:#e0a800;--rc-color-accent:#123}', FrontendModule::inline_css() );
		$this->settings( array( 'display' => array( 'star_color' => 'red;}body{display:none' ) ) );
		$this->assertSame( '', FrontendModule::inline_css(), 'stored garbage is never output' );
	}

	public function test_colour_values_are_validated_on_save(): void {
		$field = SettingsSchema::fields()['display']['star_color'];
		$this->assertSame( '#abcdef', SettingsSchema::sanitize_value( $field, ' #ABCDEF ' ) );
		$this->assertSame( '', SettingsSchema::sanitize_value( $field, '' ) );
		$this->assertSame( '', SettingsSchema::sanitize_value( $field, 'url(javascript:x)' ) );
	}

	public function test_global_widget_defaults(): void {
		$d = WidgetDefinition::from_atts( array() );
		$this->assertSame( array( 6, 'grid', 3, 2, false ), array( $d->limit, $d->layout, $d->columns, $d->heading_level, $d->new_tab ), 'defaults unchanged' );

		$this->settings(
			array(
				'display' => array(
					'widget_limit'         => 12,
					'widget_layout'        => 'list',
					'widget_columns'       => 2,
					'widget_heading_level' => 3,
					'widget_new_tab'       => true,
				),
			)
		);
		$d = WidgetDefinition::from_atts( array() );
		$this->assertSame( array( 12, 'list', 2, 3, true ), array( $d->limit, $d->layout, $d->columns, $d->heading_level, $d->new_tab ) );

		$own = WidgetDefinition::from_atts( array( 'limit' => '4', 'layout' => 'grid', 'columns' => '4', 'heading_level' => '2', 'new_tab' => '0' ) );
		$this->assertSame( array( 4, 'grid', 4, 2, false ), array( $own->limit, $own->layout, $own->columns, $own->heading_level, $own->new_tab ), 'embed values win' );

		$block = WidgetDefinition::from_block_attributes( array( 'showSummary' => true ) );
		$this->assertSame( 12, $block->limit, 'blocks without own value use the global default' );

		add_filter( 'review_captain_widget_defaults', static fn( array $v ) => array( 'limit' => 999 ) + $v );
		$this->assertSame( WidgetDefinition::MAX_LIMIT, WidgetDefinition::from_atts( array() )->limit, 'filter wins, clamped' );
	}

	public function test_block_json_has_no_defaults_for_global_attributes(): void {
		$json = json_decode( (string) file_get_contents( REVIEW_CAPTAIN_DIR . 'blocks/reviews/block.json' ), true );
		foreach ( array( 'limit', 'layout', 'columns', 'headingLevel', 'newTab' ) as $attr ) {
			$this->assertArrayNotHasKey( 'default', $json['attributes'][ $attr ], $attr . ' must fall back to the settings' );
		}
	}

	public function test_external_rating_unchanged_by_scale(): void {
		$r = $this->review( array( 'platform' => Platform::GOOGLE, 'rating_value' => 4.0 ) );
		$this->assertSame( '4 / 5', $this->renderer()->rating_data( $r )['value'] );
	}
}
