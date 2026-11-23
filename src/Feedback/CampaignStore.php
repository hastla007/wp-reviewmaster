<?php
/**
 * Feedback links with QR code and WhatsApp click-to-chat link (RC-126).
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Feedback;

use ReviewCaptain\Frontend\ProfileLookup;

/**
 * Campaigns are stored in the option `review_captain_feedback_campaigns` (no personal data):
 * `{ id, label, target, profile_id, target_url, whatsapp_number, whatsapp_text, created_at }`.
 *
 * Rules:
 * - Target is either a review profile of this site (its review URL, https) or a page on this
 *   site (same host, https) that contains the feedback form. No third-party redirectors, no
 *   tracking parameters except the campaign key `rc_campaign`.
 * - The QR SVG is always captioned "Feedback" and says it is not a ticket or admission code; it
 *   uses no ticket-like framing, numbers or barcodes, so it cannot be confused with Pass
 *   Captain admission codes.
 * - WhatsApp: only the official click-to-chat link format `https://wa.me/<number>?text=…`
 *   (https://faq.whatsapp.com – "How to use click to chat"); the plugin never sends messages and
 *   never uses unofficial APIs.
 */
final class CampaignStore {

	public const OPTION = 'review_captain_feedback_campaigns';

	/**
	 * Default maximum number of feedback links; raisable with `review_captain_max_feedback_links`
	 * up to MAX_LIMIT (SET-RC-31).
	 */
	public const MAX = 50;

	/**
	 * Upper bound for `review_captain_max_feedback_links` (all links live in one option).
	 */
	public const MAX_LIMIT = 200;

	/**
	 * Effective maximum number of feedback links.
	 */
	public static function max(): int {
		/**
		 * Maximum number of feedback links (1–200, default 50).
		 *
		 * @param int $max Maximum.
		 */
		return max( 1, min( self::MAX_LIMIT, (int) apply_filters( 'review_captain_max_feedback_links', self::MAX ) ) );
	}

	/**
	 * All campaigns keyed by id.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function all(): array {
		$stored = get_option( self::OPTION, array() );
		return is_array( $stored ) ? array_filter( $stored, 'is_array' ) : array();
	}

	/**
	 * One campaign.
	 *
	 * @param string $id ID.
	 * @return array<string, mixed>|null
	 */
	public function get( string $id ): ?array {
		return $this->all()[ $id ] ?? null;
	}

	/**
	 * Validate and save a campaign. Returns [campaign|null, errors].
	 *
	 * @param array<string, mixed> $input Input (label, target=profile|page, profile_id, target_url, whatsapp_number, whatsapp_text).
	 * @return array{0: array<string, mixed>|null, 1: list<string>}
	 */
	public function save( array $input ): array {
		$errors = array();
		$label  = sanitize_text_field( (string) ( $input['label'] ?? '' ) );
		if ( '' === $label || mb_strlen( $label ) > 80 ) {
			$errors[] = 'label';
		}
		$target     = 'profile' === ( $input['target'] ?? '' ) ? 'profile' : 'page';
		$profile_id = absint( $input['profile_id'] ?? 0 );
		$url        = '';
		if ( 'profile' === $target ) {
			$profile = $profile_id > 0 ? ( new ProfileLookup() )->find( $profile_id ) : null;
			if ( null === $profile || '' === (string) $profile['url'] ) {
				$errors[] = 'profile';
			}
		} else {
			$url = esc_url_raw( trim( (string) ( $input['target_url'] ?? '' ) ) );
			if ( ! self::is_own_https_url( $url ) ) {
				$errors[] = 'target_url';
			}
		}
		$number = preg_replace( '/\D+/', '', (string) ( $input['whatsapp_number'] ?? '' ) );
		if ( '' !== $number && ( strlen( (string) $number ) < 8 || strlen( (string) $number ) > 15 || str_starts_with( (string) $number, '0' ) ) ) {
			$errors[] = 'whatsapp_number';
		}
		$text = sanitize_textarea_field( (string) ( $input['whatsapp_text'] ?? '' ) );
		if ( mb_strlen( $text ) > 300 ) {
			$errors[] = 'whatsapp_text';
		}
		$all = $this->all();
		if ( count( $all ) >= self::max() ) {
			$errors[] = 'limit';
		}
		if ( $errors ) {
			return array( null, $errors );
		}
		$id = substr( sanitize_title( $label ), 0, 30 );
		$id = '' !== $id ? $id : 'kampagne';
		if ( isset( $all[ $id ] ) ) {
			$id .= '-' . substr( bin2hex( random_bytes( 3 ) ), 0, 6 );
		}
		$campaign   = array(
			'id'              => $id,
			'label'           => $label,
			'target'          => $target,
			'profile_id'      => 'profile' === $target ? $profile_id : 0,
			'target_url'      => 'page' === $target ? $url : '',
			'whatsapp_number' => (string) $number,
			'whatsapp_text'   => $text,
			'created_at'      => gmdate( 'Y-m-d H:i:s' ),
		);
		$all[ $id ] = $campaign;
		update_option( self::OPTION, $all, false );
		return array( $campaign, array() );
	}

	/**
	 * Delete a campaign.
	 *
	 * @param string $id ID.
	 */
	public function delete( string $id ): bool {
		$all = $this->all();
		if ( ! isset( $all[ $id ] ) ) {
			return false;
		}
		unset( $all[ $id ] );
		update_option( self::OPTION, $all, false );
		return true;
	}

	/**
	 * The feedback link of a campaign ('' when the target is gone).
	 *
	 * @param array<string, mixed> $campaign Campaign.
	 */
	public function link( array $campaign ): string {
		if ( 'profile' === ( $campaign['target'] ?? '' ) ) {
			$profile = ( new ProfileLookup() )->find( (int) ( $campaign['profile_id'] ?? 0 ) );
			// External review pages get no extra parameters (provider terms, no tracking).
			return null !== $profile ? (string) $profile['url'] : '';
		}
		$url = (string) ( $campaign['target_url'] ?? '' );
		return self::is_own_https_url( $url ) ? add_query_arg( 'rc_campaign', (string) $campaign['id'], $url ) : '';
	}

	/**
	 * Official WhatsApp click-to-chat link, '' without number.
	 *
	 * @param array<string, mixed> $campaign Campaign.
	 */
	public function whatsapp_link( array $campaign ): string {
		$number = preg_replace( '/\D+/', '', (string) ( $campaign['whatsapp_number'] ?? '' ) );
		if ( '' === $number ) {
			return '';
		}
		$text = trim( (string) ( $campaign['whatsapp_text'] ?? '' ) );
		$link = $this->link( $campaign );
		if ( '' !== $link ) {
			$text = trim( $text . ' ' . $link );
		}
		return 'https://wa.me/' . $number . ( '' !== $text ? '?text=' . rawurlencode( $text ) : '' );
	}

	/**
	 * QR code as standalone SVG with the mandatory "Feedback" caption, or '' when the link is
	 * too long for the built-in encoder (then only the link is shown).
	 *
	 * @param array<string, mixed> $campaign Campaign.
	 */
	public function qr_svg( array $campaign ): string {
		$link = $this->link( $campaign );
		if ( '' === $link ) {
			return '';
		}
		try {
			$qr = QrCode::encode( $link );
		} catch ( \LengthException $e ) {
			return '';
		}
		$quiet   = 4;
		$modules = $qr->size() + 2 * $quiet;
		$caption = __( 'Feedback geben', 'review-captain' );
		$note    = __( 'Kein Ticket – kein Einlasscode', 'review-captain' );
		$label   = (string) ( $campaign['label'] ?? '' );
		$height  = $modules + 10;
		$title   = sprintf(
			/* translators: %s: campaign label */
			__( 'QR-Code für Feedback: %s', 'review-captain' ),
			$label
		);
		return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $modules . ' ' . $height . '" width="' . ( $modules * 8 ) . '" height="' . ( $height * 8 ) . '" role="img" aria-label="' . esc_attr( $title ) . '">'
			. '<title>' . esc_html( $title ) . '</title>'
			. '<rect width="100%" height="100%" fill="#fff"/>'
			. '<path fill="#000" shape-rendering="crispEdges" d="' . esc_attr( $qr->path( $quiet ) ) . '"/>'
			. '<text x="' . ( $modules / 2 ) . '" y="' . ( $modules + 3.5 ) . '" text-anchor="middle" font-family="sans-serif" font-size="3.2" font-weight="bold" fill="#000">' . esc_html( $caption ) . '</text>'
			. '<text x="' . ( $modules / 2 ) . '" y="' . ( $modules + 7.5 ) . '" text-anchor="middle" font-family="sans-serif" font-size="2.2" fill="#333">' . esc_html( $note ) . '</text>'
			. '</svg>';
	}

	/**
	 * https URL on this site's host.
	 *
	 * @param string $url URL.
	 */
	public static function is_own_https_url( string $url ): bool {
		$parts = wp_parse_url( $url );
		$home  = wp_parse_url( home_url( '/' ) );
		return is_array( $parts ) && is_array( $home ) && 'https' === strtolower( (string) ( $parts['scheme'] ?? '' ) )
			&& '' !== (string) ( $parts['host'] ?? '' ) && strtolower( (string) $parts['host'] ) === strtolower( (string) ( $home['host'] ?? '' ) )
			&& ! isset( $parts['user'] ) && ! isset( $parts['pass'] );
	}
}
