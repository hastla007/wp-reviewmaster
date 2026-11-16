<?php
/**
 * Jobs diagnostics section (TEC-002, jobs.md §9).
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Jobs;

/**
 * Builds escaped HTML for the diagnostics page and a plain status array for CLI/doctor.
 */
final class Diagnostics {

	/**
	 * Runner health from heartbeats: ok (< 10 min), delayed (10–30 min), broken (> 30 min / never).
	 *
	 * @return array{status: string, last_run: ?int, channel: ?string}
	 */
	public static function runner_health(): array {
		$beats = get_option( Runner::OPTION_HEARTBEAT, array() );
		$beats = is_array( $beats ) ? array_filter( array_map( 'intval', $beats ) ) : array();
		if ( array() === $beats ) {
			return array(
				'status'   => 'broken',
				'last_run' => null,
				'channel'  => null,
			);
		}
		arsort( $beats );
		$channel = (string) array_key_first( $beats );
		$last    = (int) $beats[ $channel ];
		$age     = time() - $last;
		$status  = $age < 10 * MINUTE_IN_SECONDS ? 'ok' : ( $age <= 30 * MINUTE_IN_SECONDS ? 'delayed' : 'broken' );
		return array(
			'status'   => $status,
			'last_run' => $last,
			'channel'  => $channel,
		);
	}

	/**
	 * Whether DISABLE_WP_CRON is set.
	 */
	public static function wp_cron_disabled(): bool {
		return defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;
	}

	/**
	 * Render HTML (all dynamic values escaped).
	 *
	 * @param JobQueue $queue            Queue.
	 * @param bool     $action_scheduler Whether Action Scheduler is the tick source.
	 */
	public static function html( JobQueue $queue, bool $action_scheduler = false ): string {
		$stats  = $queue->stats();
		$health = self::runner_health();
		$labels = array(
			'queued'  => __( 'Wartend', 'review-captain' ),
			'running' => __( 'Laufend', 'review-captain' ),
			'paused'  => __( 'Pausiert', 'review-captain' ),
			'failed'  => __( 'Abgebrochen', 'review-captain' ),
			'dead'    => __( 'Endgültig fehlgeschlagen', 'review-captain' ),
			'overdue' => __( 'Überfällig (> 10 Minuten)', 'review-captain' ),
		);

		$out = '<table class="widefat striped rc-diagnostics-jobs"><tbody>';
		foreach ( $labels as $key => $label ) {
			$out .= '<tr><th scope="row">' . esc_html( $label ) . '</th><td>' . esc_html( number_format_i18n( (int) $stats[ $key ] ) ) . '</td></tr>';
		}
		$out .= '<tr><th scope="row">' . esc_html__( 'Ältester fälliger Job', 'review-captain' ) . '</th><td>';
		if ( null === $stats['oldest_due_at'] ) {
			$out .= esc_html__( 'keiner', 'review-captain' );
		} else {
			$ts   = (int) strtotime( $stats['oldest_due_at'] . ' UTC' );
			$out .= esc_html(
				sprintf(
					/* translators: %s: human readable duration */
					__( 'seit %s fällig', 'review-captain' ),
					human_time_diff( $ts, time() )
				)
			);
		}
		$out .= '</td></tr>';

		$health_labels = array(
			'ok'      => __( 'in Ordnung', 'review-captain' ),
			'delayed' => __( 'verzögert', 'review-captain' ),
			'broken'  => __( 'gestört', 'review-captain' ),
		);
		$out .= '<tr><th scope="row">' . esc_html__( 'Job-Runner', 'review-captain' ) . '</th><td>' . esc_html( $health_labels[ $health['status'] ] );
		if ( null !== $health['last_run'] ) {
			$out .= ' – ' . esc_html(
				sprintf(
					/* translators: 1: duration, 2: channel */
					__( 'letzter Lauf vor %1$s (%2$s)', 'review-captain' ),
					human_time_diff( $health['last_run'], time() ),
					(string) $health['channel']
				)
			);
		}
		$out .= '</td></tr>';
		$out .= '<tr><th scope="row">' . esc_html__( 'Taktgeber', 'review-captain' ) . '</th><td>' . ( $action_scheduler ? esc_html__( 'Action Scheduler', 'review-captain' ) : esc_html__( 'WP-Cron', 'review-captain' ) ) . '</td></tr>';
		$out .= '</tbody></table>';

		if ( ! self::wp_cron_disabled() ) {
			$out .= '<div class="notice notice-info inline"><p>' . esc_html__( 'Empfehlung: Für zuverlässige Ausführung einen echten System-Cron einrichten (DISABLE_WP_CRON in wp-config.php setzen und alle 5 Minuten „wp review-captain jobs run“ ausführen). Ohne System-Cron läuft WP-Cron nur bei Seitenaufrufen; Ausführungszeiten sind dann ungenau und nur als „frühestens“ zu verstehen.', 'review-captain' ) . '</p></div>';
		} elseif ( 'ok' !== $health['status'] ) {
			$out .= '<div class="notice notice-warning inline"><p>' . esc_html__( 'DISABLE_WP_CRON ist gesetzt, aber der Job-Runner lief in letzter Zeit nicht. Bitte den System-Cron prüfen.', 'review-captain' ) . '</p></div>';
		}
		if ( (int) $stats['overdue'] > 0 || 'ok' !== $health['status'] ) {
			$out .= '<p class="description">' . esc_html__( 'Geplante Zeiten sind frühestmögliche Zeitpunkte, keine Zusagen.', 'review-captain' ) . '</p>';
		}
		return $out;
	}
}
