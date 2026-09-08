<?php
/**
 * Batched background refresh.
 *
 * The dashboard never collects on a page load. A recurring cron event walks the
 * network a slice at a time, oldest data first, so a 10-site network and a
 * 10,000-site network both cost the same per request — the big network just
 * takes more passes to come fully around.
 *
 * @package ModernDashboard
 */

declare( strict_types = 1 );

namespace ModernDashboard\Cron;

use ModernDashboard\Data\MetricsRepository;
use ModernDashboard\Data\NetworkAggregator;
use ModernDashboard\Settings\Settings;

defined( 'ABSPATH' ) || exit;

final class Scheduler {

	public const HOOK          = 'modern_dashboard_refresh_batch';
	public const LOCK          = 'modern_dashboard_batch_lock';
	public const LAST_RUN      = 'modern_dashboard_last_run';
	private const LOCK_TIMEOUT = 10 * MINUTE_IN_SECONDS;

	private MetricsRepository $repository;
	private Settings $settings;

	public function __construct( MetricsRepository $repository, Settings $settings ) {
		$this->repository = $repository;
		$this->settings   = $settings;
	}

	public function register(): void {
		add_filter( 'cron_schedules', array( self::class, 'add_schedule' ) );
		add_action( self::HOOK, array( $this, 'run' ) );
		add_action( 'init', array( $this, 'ensure_scheduled' ) );

		// A brand new site should not wait a full cycle to appear.
		add_action( 'wp_initialize_site', array( $this, 'on_site_created' ), 100 );
		add_action( 'wp_delete_site', array( $this, 'on_site_deleted' ) );
	}

	/**
	 * @param array<string,array{interval:int,display:string}> $schedules Registered schedules.
	 *
	 * @return array<string,array{interval:int,display:string}>
	 */
	public static function add_schedule( array $schedules ): array {
		$schedules['mdash_quarter_hourly'] = array(
			'interval' => 15 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every 15 minutes (Modern Dashboard)', 'modern-dashboard' ),
		);

		return $schedules;
	}

	public static function schedule( string $interval ): void {
		self::unschedule();

		if ( ! in_array( $interval, Settings::INTERVALS, true ) ) {
			$interval = 'hourly';
		}

		wp_schedule_event( time() + MINUTE_IN_SECONDS, $interval, self::HOOK );
	}

	public static function unschedule(): void {
		wp_clear_scheduled_hook( self::HOOK );
	}

	public static function next_run(): ?int {
		$timestamp = wp_next_scheduled( self::HOOK );

		return $timestamp ? (int) $timestamp : null;
	}

	/**
	 * Re-schedule when the event is missing or the network admin changed the
	 * interval, so settings changes take effect without a reactivation.
	 */
	public function ensure_scheduled(): void {
		$wanted = (string) $this->settings->get( 'refresh_interval' );
		$event  = function_exists( 'wp_get_scheduled_event' ) ? wp_get_scheduled_event( self::HOOK ) : false;

		if ( ! $event || ( $event->schedule ?? '' ) !== $wanted ) {
			self::schedule( $wanted );
		}
	}

	/**
	 * Refresh one batch.
	 *
	 * @return int[] Blog IDs refreshed.
	 */
	public function run(): array {
		if ( ! $this->acquire_lock() ) {
			return array();
		}

		try {
			$refreshed = $this->repository->refresh_batch( (int) $this->settings->get( 'batch_size' ) );

			update_network_option(
				null,
				self::LAST_RUN,
				array(
					'at'    => time(),
					'count' => count( $refreshed ),
				)
			);

			NetworkAggregator::flush();

			/**
			 * Fires after a refresh batch completes.
			 *
			 * @param int[] $refreshed Blog IDs refreshed in this batch.
			 */
			do_action( 'modern_dashboard_batch_complete', $refreshed );

			return $refreshed;
		} finally {
			$this->release_lock();
		}
	}

	/**
	 * @param \WP_Site $site Newly created site.
	 */
	public function on_site_created( $site ): void {
		if ( $site instanceof \WP_Site ) {
			$this->repository->refresh( (int) $site->blog_id );
			NetworkAggregator::flush();
		}
	}

	/**
	 * @param \WP_Site $site Site being deleted.
	 */
	public function on_site_deleted( $site ): void {
		if ( $site instanceof \WP_Site ) {
			$this->repository->store()->delete( (int) $site->blog_id );
			NetworkAggregator::flush();
		}
	}

	/**
	 * Overlapping batches would collect the same sites twice and, with storage
	 * scanning on, could pile up. The lock is time-boxed so a fatal mid-batch
	 * cannot wedge collection permanently.
	 */
	private function acquire_lock(): bool {
		$existing = get_site_transient( self::LOCK );

		if ( $existing && ( time() - (int) $existing ) < self::LOCK_TIMEOUT ) {
			return false;
		}

		set_site_transient( self::LOCK, time(), self::LOCK_TIMEOUT );

		return true;
	}

	private function release_lock(): void {
		delete_site_transient( self::LOCK );
	}
}
