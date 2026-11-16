<?php
/**
 * Normalised widget definition shared by shortcodes and blocks (RC-051, RC-118).
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Widgets;

use ReviewCaptain\Domain\Platform;

/**
 * Immutable, strictly validated widget configuration.
 *
 * Shortcode attributes (snake_case) and block attributes (camelCase, mapped via
 * from_block_attributes()) end up in exactly the same normalised structure, so both
 * entry points produce identical markup.
 */
final class WidgetDefinition {

	/**
	 * Supported contract major version.
	 */
	public const CONTRACT_MAJOR = 1;

	public const DEFAULT_LIMIT = 6;
	public const MAX_LIMIT     = 50;

	/**
	 * Context types a widget/form may target (subset of Review::CONTEXT_TYPES).
	 */
	public const CONTEXT_TYPES = array( 'post', 'product', 'event', 'voucher', 'app', 'form', 'course', 'organization' );

	/**
	 * Block attribute (camelCase) → shortcode attribute (snake_case).
	 */
	private const BLOCK_MAP = array(
		'v'           => 'v',
		'type'        => 'type',
		'layout'      => 'layout',
		'columns'     => 'columns',
		'platform'    => 'platform',
		'kind'        => 'kind',
		'minRating'   => 'min_rating',
		'limit'       => 'limit',
		'contextType' => 'context_type',
		'contextId'   => 'context_id',
		'showSource'  => 'show_source',
		'showDate'    => 'show_date',
		'showAuthor'  => 'show_author',
		'showSummary' => 'show_summary',
		'paginate'    => 'paginate',
		'profile'     => 'profile',
		'newTab'      => 'new_tab',
		'headingLevel' => 'heading_level',
	);

	/**
	 * Widget type key (reviews, form, cta, or a Pro type).
	 */
	public string $type = 'reviews';

	/**
	 * Requested contract version string (as given).
	 */
	public string $version = '1';

	/**
	 * Whether the requested contract major is supported.
	 */
	public bool $compatible = true;

	public string $layout = 'grid';
	public int $columns   = 3;

	/**
	 * Platform filter (empty = all).
	 *
	 * @var list<string>
	 */
	public array $platforms = array();

	/**
	 * Kind filter: review|testimonial|all.
	 */
	public string $kind = 'all';

	/**
	 * Minimum rating on the original 1..5 scale (null = no filter).
	 */
	public ?float $min_rating = null;

	public int $limit            = self::DEFAULT_LIMIT;
	public ?string $context_type = null;
	public ?string $context_id   = null;

	/**
	 * Show source label for local reviews. External reviews always show source + link (SET-014).
	 */
	public bool $show_source = true;

	/**
	 * Tri-state display overrides (null = use settings).
	 */
	public ?bool $show_date   = null;
	public ?bool $show_author = null;

	public bool $show_summary = true;
	public bool $paginate     = false;
	public ?int $profile_id   = null;
	public bool $new_tab      = false;

	/**
	 * Heading level of card titles (2–6, default 3; QA F-07 heading-order).
	 */
	public int $heading_level = 2; // F-07/RC-137: cards usually sit directly below the page title (h1).

	/**
	 * Raw (unvalidated, but string-cast) attributes for Pro widget types.
	 *
	 * @var array<string, string>
	 */
	public array $raw = array();

	/**
	 * Validation notes for attributes that were ignored (for admins/debug only).
	 *
	 * @var list<string>
	 */
	public array $notices = array();

	/**
	 * Build from shortcode-style attributes.
	 *
	 * @param array<string|int, mixed> $atts Raw attributes.
	 * @param string                   $type Default widget type when `type` is not given.
	 */
	public static function from_atts( array $atts, string $type = 'reviews' ): self {
		$d   = new self();
		$raw = array();
		foreach ( $atts as $key => $value ) {
			if ( is_string( $key ) && ( is_scalar( $value ) || null === $value ) ) {
				$raw[ strtolower( $key ) ] = trim( (string) $value );
			}
		}
		$d->raw = $raw;

		// Contract version: missing = 1; unknown major → incompatible.
		$v          = $raw['v'] ?? '';
		$d->version = '' === $v ? '1' : $v;
		if ( ! preg_match( '/^(\d+)(?:\.\d+)*$/', $d->version, $m ) || self::CONTRACT_MAJOR !== (int) $m[1] ) {
			$d->compatible = false;
		}

		$t       = isset( $raw['type'] ) && '' !== $raw['type'] ? sanitize_key( $raw['type'] ) : $type;
		$d->type = '' !== $t ? $t : $type;

		if ( isset( $raw['layout'] ) && '' !== $raw['layout'] ) {
			if ( in_array( $raw['layout'], array( 'grid', 'list' ), true ) ) {
				$d->layout = $raw['layout'];
			} else {
				$d->notices[] = 'layout';
			}
		}

		$d->columns = self::int_in_range( $raw['columns'] ?? '', 1, 4, 3, 'columns', $d );

		if ( isset( $raw['platform'] ) && '' !== $raw['platform'] ) {
			foreach ( array_filter( array_map( 'trim', explode( ',', strtolower( $raw['platform'] ) ) ) ) as $p ) {
				if ( in_array( $p, Platform::values(), true ) ) {
					$d->platforms[] = $p;
				} else {
					$d->notices[] = 'platform';
				}
			}
			$d->platforms = array_values( array_unique( $d->platforms ) );
		}

		if ( isset( $raw['kind'] ) && '' !== $raw['kind'] ) {
			if ( in_array( $raw['kind'], array( 'review', 'testimonial', 'all' ), true ) ) {
				$d->kind = $raw['kind'];
			} else {
				$d->notices[] = 'kind';
			}
		}

		if ( isset( $raw['min_rating'] ) && '' !== $raw['min_rating'] ) {
			$mr = str_replace( ',', '.', $raw['min_rating'] );
			if ( is_numeric( $mr ) && (float) $mr >= 1.0 && (float) $mr <= 5.0 ) {
				$d->min_rating = (float) $mr;
			} else {
				$d->notices[] = 'min_rating';
			}
		}

		$d->limit = self::int_in_range( $raw['limit'] ?? '', 1, self::MAX_LIMIT, self::DEFAULT_LIMIT, 'limit', $d );

		if ( isset( $raw['context_type'] ) && '' !== $raw['context_type'] ) {
			if ( in_array( $raw['context_type'], self::CONTEXT_TYPES, true ) ) {
				$d->context_type = $raw['context_type'];
			} else {
				$d->notices[] = 'context_type';
			}
		}
		if ( isset( $raw['context_id'] ) && '' !== $raw['context_id'] ) {
			$cid = $raw['context_id'];
			if ( 'current' === $cid ) {
				$post_id = (int) get_the_ID();
				$cid     = $post_id > 0 ? (string) $post_id : '';
			}
			if ( '' !== $cid && preg_match( '/^[A-Za-z0-9_.:-]{1,64}$/', $cid ) ) {
				$d->context_id = $cid;
			} else {
				$d->notices[] = 'context_id';
			}
		}
		// A context ID without type is meaningless; ignore it.
		if ( null === $d->context_type && null !== $d->context_id ) {
			$d->context_id = null;
			$d->notices[]  = 'context_id';
		}

		$d->show_source  = self::bool_or_null( $raw['show_source'] ?? '' ) ?? true;
		$d->show_date    = self::bool_or_null( $raw['show_date'] ?? '' );
		$d->show_author  = self::bool_or_null( $raw['show_author'] ?? '' );
		$d->show_summary = self::bool_or_null( $raw['show_summary'] ?? '' ) ?? true;
		$d->paginate     = self::bool_or_null( $raw['paginate'] ?? '' ) ?? false;
		$d->new_tab      = self::bool_or_null( $raw['new_tab'] ?? '' ) ?? false;

		$d->heading_level = self::int_in_range( $raw['heading_level'] ?? '', 2, 6, 2, 'heading_level', $d );

		if ( isset( $raw['profile'] ) && '' !== $raw['profile'] ) {
			if ( ctype_digit( $raw['profile'] ) && (int) $raw['profile'] > 0 ) {
				$d->profile_id = (int) $raw['profile'];
			} else {
				$d->notices[] = 'profile';
			}
		}

		return $d;
	}

	/**
	 * Build from block attributes (camelCase). Unknown attributes are ignored.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param string               $type       Widget type.
	 */
	public static function from_block_attributes( array $attributes, string $type = 'reviews' ): self {
		return self::from_atts( self::block_to_atts( $attributes ), $type );
	}

	/**
	 * Map block attributes to shortcode attributes.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return array<string, string>
	 */
	public static function block_to_atts( array $attributes ): array {
		$atts = array();
		foreach ( self::BLOCK_MAP as $block_key => $att ) {
			if ( ! array_key_exists( $block_key, $attributes ) || null === $attributes[ $block_key ] ) {
				continue;
			}
			$value = $attributes[ $block_key ];
			if ( is_bool( $value ) ) {
				$value = $value ? '1' : '0';
			} elseif ( is_array( $value ) ) {
				$value = implode( ',', array_map( 'strval', array_filter( $value, 'is_scalar' ) ) );
			} elseif ( ! is_scalar( $value ) ) {
				continue;
			}
			$value = (string) $value;
			// Numeric block defaults of 0 mean "not set".
			if ( in_array( $att, array( 'min_rating', 'profile' ), true ) && in_array( $value, array( '0', '' ), true ) ) {
				continue;
			}
			$atts[ $att ] = $value;
		}
		return $atts;
	}

	/**
	 * Repository query args for the reviews widget. Only approved records, ever.
	 *
	 * @return array<string, mixed>
	 */
	public function query_args(): array {
		$args = array(
			'status'  => 'approved',
			'orderby' => 'published_at',
			'order'   => 'DESC',
			'limit'   => $this->limit,
		);
		if ( 'all' !== $this->kind ) {
			$args['kind'] = $this->kind;
		}
		if ( $this->platforms ) {
			$args['platform'] = $this->platforms;
		}
		if ( null !== $this->min_rating ) {
			// Attribute is given on the original 1..5 scale; repository filters normalised 0..1.
			$args['min_normalized'] = ( $this->min_rating - 1.0 ) / 4.0;
		}
		if ( null !== $this->context_type ) {
			$args['context_type'] = $this->context_type;
			if ( null !== $this->context_id ) {
				$args['context_id'] = $this->context_id;
			}
		}
		if ( null !== $this->profile_id ) {
			$args['profile_id'] = $this->profile_id;
		}
		return $args;
	}

	/**
	 * Normalised array (used for stable instance IDs and debugging).
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'type'         => $this->type,
			'v'            => $this->version,
			'layout'       => $this->layout,
			'columns'      => $this->columns,
			'platforms'    => $this->platforms,
			'kind'         => $this->kind,
			'min_rating'   => $this->min_rating,
			'limit'        => $this->limit,
			'context_type' => $this->context_type,
			'context_id'   => $this->context_id,
			'show_source'  => $this->show_source,
			'show_date'    => $this->show_date,
			'show_author'  => $this->show_author,
			'show_summary' => $this->show_summary,
			'paginate'     => $this->paginate,
			'profile_id'   => $this->profile_id,
			'new_tab'      => $this->new_tab,
			'heading_level' => $this->heading_level,
		);
	}

	/**
	 * Parse "1"/"0"/"true"/"false"/"yes"/"no"; anything else → null.
	 *
	 * @param string $value Value.
	 */
	private static function bool_or_null( string $value ): ?bool {
		$value = strtolower( $value );
		if ( in_array( $value, array( '1', 'true', 'yes', 'on' ), true ) ) {
			return true;
		}
		if ( in_array( $value, array( '0', 'false', 'no', 'off' ), true ) ) {
			return false;
		}
		return null;
	}

	/**
	 * Strict integer range validation; invalid → default (+ notice).
	 *
	 * @param string $value   Raw value.
	 * @param int    $min     Min.
	 * @param int    $max     Max.
	 * @param int    $default Default.
	 * @param string $name    Attribute name for notices.
	 * @param self   $d       Definition.
	 */
	private static function int_in_range( string $value, int $min, int $max, int $default, string $name, self $d ): int {
		if ( '' === $value ) {
			return $default;
		}
		if ( ! ctype_digit( $value ) || (int) $value < $min || (int) $value > $max ) {
			$d->notices[] = $name;
			return $default;
		}
		return (int) $value;
	}
}
