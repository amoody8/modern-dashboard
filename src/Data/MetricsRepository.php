<?php
/**
 * Read and refresh site metrics.
 *
 * Reads only ever touch the cache. Nothing in this class walks the network
 * running collectors on behalf of a page load — that is the scheduler's job.
 *
 * @package ModernDashboard
 */

declare( strict_types = 1 );

namespace ModernDashboard\Data;

use ModernDashboard\Settings\Settings;

defined( 'ABSPATH' ) || exit;

final class MetricsRepository {

	private Store $store;
	private SiteCollector $collector;

	/** @var array<string,mixed>|null Settings snapshot, read once per request. */
	private ?array $settings_cache = null;

	public function __construct( Store $store, SiteCollector $collector ) {
		$this->store     = $store;
		$this->collector = $collector;
	}

	public function store(): Store {
		return $this->store;
	}

	/**
	 * Blog IDs in the network, minus any the network admin has excluded.
	 *
	 * @return int[]
	 */
	public function site_ids(): array {
		$excluded = array_map( 'intval', (array) $this->settings( 'excluded_sites', array() ) );

		$ids = get_sites(
			array(
				'fields'   => 'ids',
				'number'   => 0,
				'orderby'  => 'id',
				'order'    => 'ASC',
				'archived' => null,
				'spam'     => null,
				'deleted'  => null,
			)
		);

		$ids = array_map( 'intval', (array) $ids );

		return array() === $excluded ? $ids : array_values( array_diff( $ids, $excluded ) );
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public function get( int $blog_id ): ?array {
		$metrics = $this->store->get( $blog_id );

		return null === $metrics ? null : $this->derive( $metrics );
	}

	/**
	 * Collect a site now and persist the result.
	 *
	 * @return array<string,mixed>|null Null when the site no longer exists.
	 */
	public function refresh( int $blog_id ): ?array {
		$metrics = $this->collector->collect( $blog_id );

		if ( null === $metrics ) {
			$this->store->delete( $blog_id );

			return null;
		}

		$this->store->set( $blog_id, $metrics );

		return $this->derive( $metrics );
	}

	/**
	 * Refresh the stalest sites.
	 *
	 * @return int[] Blog IDs refreshed.
	 */
	public function refresh_batch( int $limit ): array {
		$ids = $this->site_ids();

		$this->store->prune( $ids );

		$refreshed = array();

		foreach ( $this->store->stalest( $ids, $limit ) as $blog_id ) {
			if ( null !== $this->refresh( $blog_id ) ) {
				$refreshed[] = $blog_id;
			}
		}

		return $refreshed;
	}

	/**
	 * Every site as a dashboard row, cached data only.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function rows(): array {
		$ids = $this->site_ids();

		$this->prime_cache( $ids );

		$rows = array();

		foreach ( $ids as $blog_id ) {
			$metrics = $this->store->get( $blog_id );

			$rows[] = null === $metrics
				? $this->placeholder( $blog_id )
				: $this->derive( $metrics );
		}

		return array_values( array_filter( $rows ) );
	}

	/**
	 * Filter, sort and paginate the cached rows.
	 *
	 * @param array<string,mixed> $args Query arguments.
	 *
	 * @return array{items:array<int,array<string,mixed>>,total:int,page:int,per_page:int,pages:int}
	 */
	public function query( array $args = array() ): array {
		$args = wp_parse_args(
			$args,
			array(
				'search'   => '',
				'status'   => 'all',
				'flag'     => 'all',
				'orderby'  => 'name',
				'order'    => 'asc',
				'page'     => 1,
				'per_page' => 20,
			)
		);

		$rows = $this->filter( $this->rows(), (string) $args['search'], (string) $args['status'], (string) $args['flag'] );
		$rows = $this->sort( $rows, (string) $args['orderby'], (string) $args['order'] );

		$total    = count( $rows );
		$per_page = max( 1, min( 200, (int) $args['per_page'] ) );
		$pages    = max( 1, (int) ceil( $total / $per_page ) );
		$page     = max( 1, min( $pages, (int) $args['page'] ) );

		return array(
			'items'    => array_slice( $rows, ( $page - 1 ) * $per_page, $per_page ),
			'total'    => $total,
			'page'     => $page,
			'per_page' => $per_page,
			'pages'    => $pages,
		);
	}

	/**
	 * @param array<int,array<string,mixed>> $rows Rows to filter.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function filter( array $rows, string $search, string $status, string $flag ): array {
		$search = strtolower( trim( $search ) );

		return array_values(
			array_filter(
				$rows,
				static function ( array $row ) use ( $search, $status, $flag ): bool {
					if ( '' !== $search ) {
						$haystack = strtolower( $row['name'] . ' ' . $row['domain'] . $row['path'] . ' ' . $row['url'] );

						if ( ! str_contains( $haystack, $search ) ) {
							return false;
						}
					}

					$flags = $row['flags'];

					$matches_status = match ( $status ) {
						'public'   => $flags['public'] && ! $flags['archived'] && ! $flags['spam'] && ! $flags['deleted'],
						'private'  => ! $flags['public'],
						'archived' => $flags['archived'],
						'spam'     => $flags['spam'],
						'deleted'  => $flags['deleted'],
						default    => true,
					};

					if ( ! $matches_status ) {
						return false;
					}

					return match ( $flag ) {
						'needs_updates' => $row['totals']['updates'] > 0,
						'inactive'      => in_array( 'inactive', $row['attention'], true ),
						'attention'     => array() !== $row['attention'],
						'stale'         => $row['stale'],
						default         => true,
					};
				}
			)
		);
	}

	/**
	 * @param array<int,array<string,mixed>> $rows Rows to sort.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function sort( array $rows, string $orderby, string $order ): array {
		$direction = 'desc' === strtolower( $order ) ? -1 : 1;

		$value = static function ( array $row ) use ( $orderby ): mixed {
			return match ( $orderby ) {
				'users'          => $row['totals']['users'],
				'content'        => $row['totals']['content'],
				'posts'          => $row['content']['posts'] ?? 0,
				'storage'        => $row['totals']['storage'] ?? 0,
				'updates'        => $row['totals']['updates'],
				'last_published' => $row['content']['last_published'] ?? 0,
				'registered'     => $row['registered'] ?? 0,
				'collected_at'   => $row['collected_at'] ?? 0,
				'id'             => $row['blog_id'],
				default          => strtolower( (string) $row['name'] ),
			};
		};

		usort(
			$rows,
			static function ( array $a, array $b ) use ( $value, $direction ): int {
				$left  = $value( $a );
				$right = $value( $b );

				$comparison = is_string( $left ) || is_string( $right )
					? strcmp( (string) $left, (string) $right )
					// Null sorts as 0 so never-collected sites cluster predictably.
					: ( (float) $left <=> (float) $right );

				return $comparison * $direction;
			}
		);

		return $rows;
	}

	/**
	 * Add computed fields the UI needs so the client never recomputes them.
	 *
	 * @param array<string,mixed> $metrics Stored metrics.
	 *
	 * @return array<string,mixed>
	 */
	private function derive( array $metrics ): array {
		$content = is_array( $metrics['content'] ?? null ) ? $metrics['content'] : array();
		$users   = is_array( $metrics['users'] ?? null ) ? $metrics['users'] : array();
		$updates = is_array( $metrics['updates'] ?? null ) ? $metrics['updates'] : array();
		$storage = is_array( $metrics['storage'] ?? null ) ? $metrics['storage'] : array();

		$metrics['totals'] = array(
			'users'   => (int) ( $users['total'] ?? 0 ),
			'content' => (int) ( $content['posts'] ?? 0 ) + (int) ( $content['pages'] ?? 0 ),
			'media'   => (int) ( $content['media'] ?? 0 ),
			'updates' => (int) ( $updates['plugin_updates'] ?? 0 ) + (int) ( $updates['theme_updates'] ?? 0 ),
			'storage' => $storage['bytes'] ?? null,
		);

		$collected_at               = (int) ( $metrics['collected_at'] ?? 0 );
		$metrics['stale']           = ( time() - $collected_at ) > (int) $this->settings( 'stale_after', 2 * HOUR_IN_SECONDS );
		$metrics['never_collected'] = false;

		$metrics['attention'] = $this->attention_reasons( $metrics, $content );

		return $metrics;
	}

	/**
	 * @param array<string,mixed> $metrics Derived metrics so far.
	 * @param array<string,mixed> $content Content payload.
	 *
	 * @return string[]
	 */
	private function attention_reasons( array $metrics, array $content ): array {
		$reasons = array();
		$flags   = (array) ( $metrics['flags'] ?? array() );

		if ( $metrics['totals']['updates'] > 0 ) {
			$reasons[] = 'updates';
		}

		if ( ! empty( $flags['spam'] ) ) {
			$reasons[] = 'spam';
		}

		if ( ! empty( $flags['archived'] ) ) {
			$reasons[] = 'archived';
		}

		if ( ! empty( $flags['deleted'] ) ) {
			$reasons[] = 'deleted';
		}

		if ( ! empty( $content['comments_pending'] ) ) {
			$reasons[] = 'moderation';
		}

		$threshold = (int) $this->settings( 'inactive_threshold_days', 90 ) * DAY_IN_SECONDS;
		$last      = $content['last_published'] ?? null;

		if ( null === $last || ( time() - (int) $last ) > $threshold ) {
			$reasons[] = 'inactive';
		}

		if ( ! empty( $metrics['errors'] ) ) {
			$reasons[] = 'collection_error';
		}

		return $reasons;
	}

	/**
	 * A row for a site that has never been collected, so the network admin sees
	 * every site immediately rather than waiting for the first cron pass.
	 *
	 * @return array<string,mixed>|null
	 */
	private function placeholder( int $blog_id ): ?array {
		$site = get_site( $blog_id );

		if ( ! $site instanceof \WP_Site ) {
			return null;
		}

		return array(
			'blog_id'         => (int) $site->blog_id,
			'name'            => $site->blogname ?: $site->domain,
			'url'             => untrailingslashit( $site->siteurl ?: ( 'https://' . $site->domain . $site->path ) ),
			'domain'          => (string) $site->domain,
			'path'            => (string) $site->path,
			'registered'      => null,
			'last_updated'    => null,
			'flags'           => array(
				'public'   => (bool) (int) $site->public,
				'archived' => (bool) (int) $site->archived,
				'spam'     => (bool) (int) $site->spam,
				'deleted'  => (bool) (int) $site->deleted,
				'mature'   => (bool) (int) $site->mature,
			),
			'content'         => array(),
			'users'           => array(),
			'updates'         => array(),
			'storage'         => array(),
			'errors'          => array(),
			'collected_at'    => 0,
			'collection_ms'   => 0,
			'totals'          => array(
				'users'   => 0,
				'content' => 0,
				'media'   => 0,
				'updates' => 0,
				'storage' => null,
			),
			'stale'           => true,
			'never_collected' => true,
			'attention'       => array(),
		);
	}

	/**
	 * One query for every site's metrics instead of one query per site.
	 *
	 * @param int[] $ids Blog IDs.
	 */
	private function prime_cache( array $ids ): void {
		if ( array() === $ids || ! function_exists( 'is_site_meta_supported' ) || ! is_site_meta_supported() ) {
			return;
		}

		update_meta_cache( 'blog', $ids );
	}

	/**
	 * Read a setting. Cached for the request: `derive()` calls this for every
	 * row, and on a large network that is thousands of lookups.
	 */
	private function settings( string $key, mixed $fallback ): mixed {
		if ( null === $this->settings_cache ) {
			$stored = get_network_option( null, Settings::OPTION, array() );

			$this->settings_cache = is_array( $stored ) ? $stored : array();
		}

		return array_key_exists( $key, $this->settings_cache ) ? $this->settings_cache[ $key ] : $fallback;
	}
}
