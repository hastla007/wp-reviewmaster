<?php
declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\Widgets;

use ReviewCaptain\Widgets\WidgetDefinition;
use ReviewCaptain\Widgets\WidgetRegistry;
use ReviewCaptain\Tests\TestCase;

final class WidgetDefinitionTest extends TestCase {

	public function test_defaults_and_missing_version_means_v1(): void {
		$d = WidgetDefinition::from_atts( array() );
		$this->assertTrue( $d->compatible );
		$this->assertSame( '1', $d->version );
		$this->assertSame( 'reviews', $d->type );
		$this->assertSame( 'grid', $d->layout );
		$this->assertSame( 6, $d->limit );
		$this->assertSame( 'all', $d->kind );
		$this->assertTrue( $d->show_source );
		$this->assertNull( $d->show_date );
		$this->assertSame( 'approved', $d->query_args()['status'] );
	}

	public function test_unknown_major_version_is_incompatible(): void {
		$this->assertFalse( WidgetDefinition::from_atts( array( 'v' => '2' ) )->compatible );
		$this->assertFalse( WidgetDefinition::from_atts( array( 'v' => 'abc' ) )->compatible );
		$this->assertTrue( WidgetDefinition::from_atts( array( 'v' => '1.3' ) )->compatible );

		$registry = new WidgetRegistry();
		$registry->register_core( 'reviews', static fn() => '<div>x</div>', 'Reviews' );
		$out = $registry->render( WidgetDefinition::from_atts( array( 'v' => '2' ) ) );
		$this->assertStringStartsWith( '<!--', $out );
		$this->assertStringNotContainsString( '<div', $out );
	}

	public function test_limit_is_strictly_validated(): void {
		$this->assertSame( 1, WidgetDefinition::from_atts( array( 'limit' => '1' ) )->limit );
		$this->assertSame( 50, WidgetDefinition::from_atts( array( 'limit' => '50' ) )->limit );
		foreach ( array( '0', '51', '-3', 'abc', '5.5', '1e3' ) as $bad ) {
			$d = WidgetDefinition::from_atts( array( 'limit' => $bad ) );
			$this->assertSame( 6, $d->limit, $bad );
			$this->assertContains( 'limit', $d->notices );
		}
	}

	public function test_min_rating_on_original_scale_maps_to_normalized(): void {
		$d = WidgetDefinition::from_atts( array( 'min_rating' => '4' ) );
		$this->assertSame( 4.0, $d->min_rating );
		$this->assertSame( 0.75, $d->query_args()['min_normalized'] );
		$this->assertSame( 0.0, WidgetDefinition::from_atts( array( 'min_rating' => '1' ) )->query_args()['min_normalized'] );
		$this->assertSame( 0.875, WidgetDefinition::from_atts( array( 'min_rating' => '4,5' ) )->query_args()['min_normalized'] );
		foreach ( array( '0', '6', 'x', '0.5' ) as $bad ) {
			$this->assertNull( WidgetDefinition::from_atts( array( 'min_rating' => $bad ) )->min_rating, $bad );
		}
	}

	public function test_whitelists(): void {
		$d = WidgetDefinition::from_atts(
			array(
				'platform'     => 'google, Trustpilot,evil,local',
				'kind'         => 'hacked',
				'layout'       => 'carousel',
				'context_type' => 'users',
				'context_id'   => '5',
			)
		);
		$this->assertSame( array( 'google', 'trustpilot', 'local' ), $d->platforms );
		$this->assertSame( 'all', $d->kind );
		$this->assertSame( 'grid', $d->layout );
		$this->assertNull( $d->context_type );
		$this->assertNull( $d->context_id, 'context id without valid type is dropped' );

		$ok = WidgetDefinition::from_atts( array( 'kind' => 'testimonial', 'layout' => 'list', 'context_type' => 'product', 'context_id' => '42' ) );
		$this->assertSame( 'testimonial', $ok->kind );
		$this->assertSame( 'list', $ok->layout );
		$this->assertSame( array( 'kind' => 'testimonial', 'context_type' => 'product', 'context_id' => '42' ), array_intersect_key( $ok->query_args(), array_flip( array( 'kind', 'context_type', 'context_id' ) ) ) );

		$this->assertNull( WidgetDefinition::from_atts( array( 'context_type' => 'post', 'context_id' => "1' OR 1=1" ) )->context_id );
		$this->assertNull( WidgetDefinition::from_atts( array( 'context_type' => 'post', 'context_id' => 'current' ) )->context_id, 'no current post in tests' );
	}

	public function test_kind_all_has_no_kind_filter(): void {
		$this->assertArrayNotHasKey( 'kind', WidgetDefinition::from_atts( array( 'kind' => 'all' ) )->query_args() );
	}

	public function test_block_attributes_map_to_same_definition(): void {
		$block = WidgetDefinition::from_block_attributes(
			array(
				'layout'      => 'list',
				'platform'    => 'google',
				'kind'        => 'review',
				'minRating'   => 4,
				'limit'       => 3,
				'contextType' => '',
				'contextId'   => '',
				'profile'     => 0,
				'showSource'  => false,
				'showDate'    => false,
			)
		);
		$short = WidgetDefinition::from_atts(
			array(
				'layout'      => 'list',
				'platform'    => 'google',
				'kind'        => 'review',
				'min_rating'  => '4',
				'limit'       => '3',
				'show_source' => '0',
				'show_date'   => '0',
			)
		);
		$this->assertSame( $short->to_array(), $block->to_array() );
	}

	public function test_profile_attribute(): void {
		$this->assertSame( 7, WidgetDefinition::from_atts( array( 'profile' => '7' ) )->profile_id );
		$this->assertNull( WidgetDefinition::from_atts( array( 'profile' => '7a' ) )->profile_id );
	}
}
