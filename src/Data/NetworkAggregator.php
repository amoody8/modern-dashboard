<?php
/**
 * Rolls the per-site cache up into a single network view.
 *
 * @package ModernDashboard
 */

declare( strict_types = 1 );

namespace ModernDashboard\Data;

use ModernDashboard\Cron\Scheduler;

defined( 'ABSPATH' ) || exit;

final class NetworkAggregator {

	public const TRANSIENT = 'modern_dashboard_overview';

	/** Short cache: the underlying data only moves when cron runs anyway. */
	private const TTL = 2 * MINUTE_IN_SECONDS;

	private MetricsRepository $repository;

	public function __construct( MetricsRepository $repository ) {
		$this->repository = $repository;
	}

	public static function flush(): void {
		delete_site_transient( self::TRANSIENT );
	}

	/**
	 * @return array<string,mixed>
	 */
	public function overview( bool $use_cache = true ): array {
		if ( $use_cache ) {
			$cached = get_site_transient( self::TRANSIENT );

			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$overview = $this->build();

		set_site_transient( self::TRANSIENT, $overview, self::TTL );

		return $overview;
	}

	/**
	 * @return array<string,mixed>
	 */
	private function build(): array {
		$rows = $this->repository->rows();

		$totals = array(
			'sites'            => count( $rows ),
			'public'           => 0,
			'private'          => 0,
			'archived'         => 0,
			'spam'             => 0,
			'deleted'          => 0,
			// Sum of per-site memberships. A user on five sites counts five
			// times here, which is what "seats to administer" means; see
			// `users.unique` for the distinct headcount.
			'site_memberships' => 0,
			'administrators'   => 0,
			'posts'            => 0,
			'pages'            => 0,
			'media'            => 0,
			'comments'         => 0,
			'comments_pending' => 0,
			'plugin_updates'   => 0,
			'theme_updates'    => 0,
			'storage_bytes'    => 0,
			'storage_partial'  => false,
		);

		$attention       = array();
		$never_collected = 0;
		$stale           = 0;
		$oldest          = null;

		foreach ( $rows as $row ) {
			$flags = $row['flags'];

			$totals['public']   += $flags['public'] && ! $flags['archived'] && ! $flags['spam'] && ! $flags['deleted'] ? 1 : 0;
			$totals['private']  += $flags['public'] ? 0 : 1;
			$totals['archived'] += $flags['archived'] ? 1 : 0;
			$totals['spam']     += $flags['spam'] ? 1 : 0;
			$totals['deleted']  += $flags['deleted'] ? 1 : 0;

			$totals['site_memberships'] += (int) ( $row['users']['total'] ?? 0 );
			$totals['administrators']   += (int) ( $row['users']['administrators'] ?? 0 );
			$totals['posts']            += (int) ( $row['content']['posts'] ?? 0 );
			$totals['pages']            += (int) ( $row['content']['pages'] ?? 0 );
			$totals['media']            += (int) ( $row['content']['media'] ?? 0 );
			$totals['comments']         += (int) ( $row['content']['comments'] ?? 0 );
			$totals['comments_pending'] += (int) ( $row['content']['comments_pending'] ?? 0 );
			$totals['plugin_updates']   += (int) ( $row['updates']['plugin_updates'] ?? 0 );
			$totals['theme_updates']    += (int) ( $row['updates']['theme_updates'] ?? 0 );

			$bytes = $row['storage']['bytes'] ?? null;

			if ( is_int( $bytes ) ) {
				$totals['storage_bytes'] += $bytes;
			}

			if ( ! empty( $row['storage']['partial'] ) ) {
				$totals['storage_partial'] = true;
			}

			if ( ! empty( $row['never_collected'] ) ) {
				++$never_collected;
			} elseif ( ! empty( $row['stale'] ) ) {
				++$stale;
			}

			$collected = (int) ( $row['collected_at'] ?? 0 );

			if ( $collected > 0 && ( null === $oldest || $collected < $oldest ) ) {
				$oldest = $collected;
			}

			if ( array() !== $row['attention'] ) {
				$attention[] = array(
					'blog_id'  => $row['blog_id'],
					'name'     => $row['name'],
					'url'      => $row['url'],
					'reasons'  => $row['attention'],
					'updates'  => $row['totals']['updates'],
					'severity' => $this->severity( $row['attention'] ),
				);
			}
		}

		usort( $attention, static fn( array $a, array $b ): int => $b['severity'] <=> $a['severity'] );

		return array(
			'totals'          => $totals,
			'users'           => array(
				'unique'      => (int) get_user_count(),
				'memberships' => $totals['site_memberships'],
			),
			'attention'       => array_slice( $attention, 0, 25 ),
			'attention_count' => count( $attention ),
			'freshness'       => array(
				'never_collected'     => $never_collected,
				'stale'               => $stale,
				'oldest_collected_at' => $oldest,
				'next_run'            => Scheduler::next_run(),
			),
			'environment'     => $this->environment(),
			'generated_at'    => time(),
		);
	}

	/**
	 * Rank attention reasons so the most actionable sites float to the top.
	 *
	 * @param string[] $reasons Attention reasons.
	 */
	private function severity( array $reasons ): int {
		$weights = array(
			'collection_error' => 5,
			'spam'             => 4,
			'updates'          => 3,
			'deleted'          => 3,
			'moderation'       => 2,
			'archived'         => 1,
			'inactive'         => 1,
		);

		$score = 0;

		foreach ( $reasons as $reason ) {
			$score += $weights[ $reason ] ?? 0;
		}

		return $score;
	}

	/**
	 * @return array<string,mixed>
	 */
	private function environment(): array {
		global $wp_version, $wpdb;

		$core        = get_site_transient( 'update_core' );
		$core_update = null;

		if ( is_object( $core ) && ! empty( $core->updates ) ) {
			foreach ( $core->updates as $update ) {
				if ( isset( $update->response ) && 'upgrade' === $update->response ) {
					$core_update = (string) $update->current;
					break;
				}
			}
		}

		$network = get_network();

		return array(
			'network_id'   => $network ? (int) $network->id : 1,
			'network_name' => $network ? (string) $network->site_name : '',
			'wp_version'   => (string) $wp_version,
			'core_update'  => $core_update,
			'php_version'  => PHP_VERSION,
			'db_version'   => $wpdb->db_version(),
			'subdomain'    => (bool) ( defined( 'SUBDOMAIN_INSTALL' ) && SUBDOMAIN_INSTALL ),
			'site_meta'    => function_exists( 'is_site_meta_supported' ) && is_site_meta_supported(),
		);
	}
}
