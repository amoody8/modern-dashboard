<?php
/**
 * Runs the collectors against one site.
 *
 * @package ModernDashboard
 */

declare( strict_types = 1 );

namespace ModernDashboard\Data;

use ModernDashboard\Data\Collectors\Collector;
use ModernDashboard\Data\Collectors\ContentCollector;
use ModernDashboard\Data\Collectors\StorageCollector;
use ModernDashboard\Data\Collectors\UpdatesCollector;
use ModernDashboard\Data\Collectors\UserCollector;
use ModernDashboard\Settings\Settings;

defined( 'ABSPATH' ) || exit;

final class SiteCollector {

	private Settings $settings;

	/** @var Collector[]|null */
	private ?array $collectors = null;

	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * @return Collector[]
	 */
	public function collectors(): array {
		if ( null === $this->collectors ) {
			/**
			 * Filters the collectors run against every site.
			 *
			 * @param Collector[] $collectors Registered collectors.
			 */
			$collectors = apply_filters(
				'modern_dashboard_collectors',
				array(
					new ContentCollector(),
					new UserCollector(),
					new UpdatesCollector(),
					new StorageCollector(),
				)
			);

			$this->collectors = array_values(
				array_filter( $collectors, static fn( $c ): bool => $c instanceof Collector )
			);
		}

		return $this->collectors;
	}

	/**
	 * Collect a single site.
	 *
	 * @return array<string,mixed>|null Null when the site no longer exists.
	 */
	public function collect( int $blog_id ): ?array {
		$site = get_site( $blog_id );

		if ( ! $site instanceof \WP_Site ) {
			return null;
		}

		$started = microtime( true );

		$payload = array(
			'blog_id'      => (int) $site->blog_id,
			'name'         => $site->blogname ?: $site->domain,
			'url'          => untrailingslashit( $site->siteurl ?: ( 'https://' . $site->domain . $site->path ) ),
			'domain'       => (string) $site->domain,
			'path'         => (string) $site->path,
			'registered'   => $this->to_timestamp( $site->registered ),
			'last_updated' => $this->to_timestamp( $site->last_updated ),
			'flags'        => array(
				'public'   => (bool) (int) $site->public,
				'archived' => (bool) (int) $site->archived,
				'spam'     => (bool) (int) $site->spam,
				'deleted'  => (bool) (int) $site->deleted,
				'mature'   => (bool) (int) $site->mature,
			),
			'errors'       => array(),
		);

		switch_to_blog( $blog_id );

		try {
			foreach ( $this->collectors() as $collector ) {
				try {
					$payload[ $collector->key() ] = $collector->collect( $site, $this->settings );
				} catch ( \Throwable $e ) {
					// One misbehaving collector (or a plugin hooked into it) must
					// not cost us the rest of the site's metrics.
					$payload[ $collector->key() ] = null;
					$payload['errors'][]          = array(
						'collector' => $collector->key(),
						'message'   => $e->getMessage(),
					);
				}
			}
		} finally {
			restore_current_blog();
		}

		$payload['collected_at']  = time();
		$payload['collection_ms'] = (int) round( ( microtime( true ) - $started ) * 1000 );

		/**
		 * Filters a site's collected metrics before they are stored.
		 *
		 * @param array<string,mixed> $payload Collected metrics.
		 * @param \WP_Site            $site    The site collected.
		 */
		return apply_filters( 'modern_dashboard_site_metrics', $payload, $site );
	}

	private function to_timestamp( ?string $datetime ): ?int {
		if ( ! $datetime || '0000-00-00 00:00:00' === $datetime ) {
			return null;
		}

		$timestamp = strtotime( $datetime . ' UTC' );

		return false === $timestamp ? null : $timestamp;
	}
}
