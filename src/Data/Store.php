<?php
/**
 * Persistence for per-site metrics.
 *
 * Metrics are written to site meta where the network supports it (the
 * `wp_blogmeta` table, added in WordPress 5.1 and created by a network upgrade)
 * and fall back to per-site network options otherwise, so a network that has
 * not run its upgrade routine still works.
 *
 * A separate lightweight index maps blog ID to collection timestamp. It lets the
 * scheduler answer "which sites are stalest?" with a single option read instead
 * of a meta query across the whole network.
 *
 * @package ModernDashboard
 */

declare( strict_types = 1 );

namespace ModernDashboard\Data;

defined( 'ABSPATH' ) || exit;

final class Store {

	public const META_KEY     = '_modern_dashboard_metrics';
	public const INDEX_OPTION = 'modern_dashboard_index';

	/** @var array<int,int>|null Blog ID => collected_at. */
	private ?array $index = null;

	/**
	 * @return array<string,mixed>|null Null when the site has never been collected.
	 */
	public function get( int $blog_id ): ?array {
		$data = $this->supports_site_meta()
			? get_site_meta( $blog_id, self::META_KEY, true )
			: get_network_option( null, $this->option_name( $blog_id ), null );

		return is_array( $data ) && array() !== $data ? $data : null;
	}

	/**
	 * @param int                 $blog_id Site to write.
	 * @param array<string,mixed> $data    Collected metrics.
	 */
	public function set( int $blog_id, array $data ): void {
		if ( $this->supports_site_meta() ) {
			update_site_meta( $blog_id, self::META_KEY, $data );
		} else {
			update_network_option( null, $this->option_name( $blog_id ), $data );
		}

		$this->index_set( $blog_id, (int) ( $data['collected_at'] ?? time() ) );
	}

	public function delete( int $blog_id ): void {
		if ( $this->supports_site_meta() ) {
			delete_site_meta( $blog_id, self::META_KEY );
		} else {
			delete_network_option( null, $this->option_name( $blog_id ) );
		}

		$this->index_set( $blog_id, null );
	}

	/**
	 * Collection timestamps keyed by blog ID.
	 *
	 * @return array<int,int>
	 */
	public function index(): array {
		if ( null === $this->index ) {
			$stored      = get_network_option( null, self::INDEX_OPTION, array() );
			$this->index = is_array( $stored ) ? array_map( 'intval', $stored ) : array();
		}

		return $this->index;
	}

	/**
	 * When a site has never been collected it sorts ahead of every collected
	 * site, so new sites in the network get picked up on the next cron run.
	 *
	 * @param int[] $candidate_ids Blog IDs eligible for collection.
	 *
	 * @return int[] Stalest first, capped at $limit.
	 */
	public function stalest( array $candidate_ids, int $limit ): array {
		$index = $this->index();
		$aged  = array();

		foreach ( $candidate_ids as $blog_id ) {
			$aged[ (int) $blog_id ] = $index[ (int) $blog_id ] ?? 0;
		}

		asort( $aged, SORT_NUMERIC );

		return array_slice( array_keys( $aged ), 0, max( 0, $limit ) );
	}

	/**
	 * Drop index entries for sites that no longer exist in the network.
	 *
	 * @param int[] $existing_ids Blog IDs currently in the network.
	 */
	public function prune( array $existing_ids ): void {
		$index    = $this->index();
		$existing = array_flip( array_map( 'intval', $existing_ids ) );
		$kept     = array_intersect_key( $index, $existing );

		if ( count( $kept ) !== count( $index ) ) {
			$this->index = $kept;
			update_network_option( null, self::INDEX_OPTION, $kept );
		}
	}

	private function index_set( int $blog_id, ?int $timestamp ): void {
		$index = $this->index();

		if ( null === $timestamp ) {
			unset( $index[ $blog_id ] );
		} else {
			$index[ $blog_id ] = $timestamp;
		}

		$this->index = $index;
		update_network_option( null, self::INDEX_OPTION, $index );
	}

	private function option_name( int $blog_id ): string {
		return 'modern_dashboard_metrics_' . $blog_id;
	}

	private function supports_site_meta(): bool {
		return function_exists( 'is_site_meta_supported' ) && is_site_meta_supported();
	}
}
