<?php
/**
 * SET-RC-31 limits raisable via filters with upper bounds, SET-RC-27 photo edge.
 */

declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\Settings;

use ReviewCaptain\Feedback\CampaignStore;
use ReviewCaptain\Media\PhotoStore;
use ReviewCaptain\Moderation\TagStore;
use ReviewCaptain\Plugin;
use ReviewCaptain\Tests\TestCase;

final class RaisableLimitsTest extends TestCase {

	public function test_tag_limit_filter_with_upper_bound(): void {
		$input = implode( ',', range( 1, 80 ) );
		$this->assertCount( 20, TagStore::normalize( $input ) );
		add_filter( 'review_captain_max_tags', static fn() => 30 );
		$this->assertCount( 30, TagStore::normalize( $input ) );
		add_filter( 'review_captain_max_tags', static fn() => 500, 20 );
		$this->assertCount( TagStore::MAX_TAGS_LIMIT, TagStore::normalize( $input ) );
	}

	public function test_feedback_link_limit_filter_with_upper_bound(): void {
		$this->assertSame( 50, CampaignStore::max() );
		add_filter( 'review_captain_max_feedback_links', static fn() => 120 );
		$this->assertSame( 120, CampaignStore::max() );
		add_filter( 'review_captain_max_feedback_links', static fn() => 100000, 20 );
		$this->assertSame( CampaignStore::MAX_LIMIT, CampaignStore::max() );
		add_filter( 'review_captain_max_feedback_links', static fn() => 0, 30 );
		$this->assertSame( 1, CampaignStore::max() );
	}

	public function test_feedback_link_limit_is_enforced(): void {
		add_filter( 'review_captain_max_feedback_links', static fn() => 1 );
		$store = new CampaignStore();
		update_option( CampaignStore::OPTION, array( 'x' => array( 'id' => 'x' ) ) );
		$result = $store->save( array( 'label' => 'Theke' ) );
		$this->assertContains( 'limit', $result[1] );
	}

	public function test_photo_edge_setting_and_filter(): void {
		$this->assertSame( 1600, PhotoStore::max_edge() );
		update_option( 'review_captain_settings', array( 'submissions' => array( 'photo_max_edge' => 1200 ) ) );
		Plugin::instance()->settings()->flush();
		$this->assertSame( 1200, PhotoStore::max_edge() );
		add_filter( 'review_captain_photo_max_edge', static fn() => 99999 );
		$this->assertSame( 4000, PhotoStore::max_edge(), 'clamped' );
	}

	public function test_photo_is_downscaled_to_setting(): void {
		if ( ! PhotoStore::can_process() ) {
			$this->markTestSkipped( 'GD not available' );
		}
		update_option( 'review_captain_settings', array( 'submissions' => array( 'photo_max_edge' => 400 ) ) );
		Plugin::instance()->settings()->flush();
		$img  = imagecreatetruecolor( 1000, 500 );
		$file = tempnam( sys_get_temp_dir(), 'rcp' );
		imagepng( $img, (string) $file );
		$out = PhotoStore::process( (string) $file );
		unlink( (string) $file );
		$this->assertSame( array( 400, 200 ), array( $out['width'], $out['height'] ) );
	}
}
