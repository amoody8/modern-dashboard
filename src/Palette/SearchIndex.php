<?php
/**
 * Persistence for the cross-network search index.
 *
 * Deliberately a second store rather than another key inside the metrics
 * payload: `MetricsRepository::rows()` reads every site's metrics for the site
 * table, and index entries are an order of magnitude larger than the counts
 * that record holds. Growing the hot path to serve the cold one would be a
 * regression on a screen people use constantly.
 *
 * The shape mirrors `Store` — bulk payload in site meta with a network-option
 * fallback, plus one compact option mapping blog ID to index time so "which
 * sites are stalest?" stays a single read.
 *
 * @package ModernDashboard
 */

declare( strict_types = 1 );

namespace ModernDashboard\Palette;

defined( 'ABSPATH' ) || exit;

final class SearchIndex {

	public const META_KEY     = '_modern_dashboard_search';
	public const INDEX_OPTION = 'modern_dashboard_search_index';

	/**
	 * Entries kept per site.
	 *
	 * Bounds both the stored row and the fan-in when a super admin searches: a
	 * network-wide query walks this many entries per site, so the number is a
	 * latency budget as much as a storage one.
	 */
	public const MAX_ENTRIES = 60;

	/** @var array<int,int>|null Blog ID => indexed_at. */
	private ?array $index = null;

	/**
	 * @return array<string,mixed>|null Null when the site has never been indexed.
	 */
	public function get( int $blog_id ): ?array {
		$data = $this->supports_site_meta()
			? get_site_meta( $blog_id, self::META_KEY, true )
			: get_network_option( null, $this->option_name( $blog_id ), null );

		return is_array( $data ) && array() !== $data ? $data : null;
	}

	/**
	 * @param int                 $blog_id Site to write.
	 * @param array<string,mixed> $record  Site envelope plus its entries.
	 */
	public function set( int $blog_id, array $record ): void {
		if ( $this->supports_site_meta() ) {
			update_site_meta( $blog_id, self::META_KEY, $record );
		} else {
			update_network_option( null, $this->option_name( $blog_id ), $record );
		}

		$this->index_set( $blog_id, (int) ( $record['indexed_at'] ?? time() ) );
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
	 * Index timestamps keyed by blog ID.
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
	 * Records for several sites at once.
	 *
	 * Primes the meta cache in a single query first, so reading N sites costs
	 * one round trip rather than N.
	 *
	 * @param int[] $blog_ids Sites to read.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function records( array $blog_ids ): array {
		$blog_ids = array_values( array_unique( array_map( 'intval', $blog_ids ) ) );

		if ( array() === $blog_ids ) {
			return array();
		}

		if ( $this->supports_site_meta() && function_exists( 'update_meta_cache' ) ) {
			update_meta_cache( 'blog', $blog_ids );
		}

		$records = array();

		foreach ( $blog_ids as $blog_id ) {
			$record = $this->get( $blog_id );

			if ( null !== $record ) {
				$records[ $blog_id ] = $record;
			}
		}

		return $records;
	}

	/**
	 * Sites that have never been indexed sort first, so a new site joins the
	 * index on the next pass rather than waiting a full cycle.
	 *
	 * @param int[] $candidate_ids Blog IDs eligible for indexing.
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
	 * The oldest index time among the given sites, for telling the reader how
	 * fresh their network results actually are.
	 *
	 * @param int[] $blog_ids Sites contributing to a result set.
	 */
	public function oldest( array $blog_ids ): ?int {
		$index = $this->index();
		$times = array();

		foreach ( $blog_ids as $blog_id ) {
			if ( isset( $index[ (int) $blog_id ] ) ) {
				$times[] = $index[ (int) $blog_id ];
			}
		}

		return array() === $times ? null : min( $times );
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
		return 'modern_dashboard_search_' . $blog_id;
	}

	private function supports_site_meta(): bool {
		return function_exists( 'is_site_meta_supported' ) && is_site_meta_supported();
	}
}
