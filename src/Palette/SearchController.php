<?php
/**
 * Assembles a search response from the live query and the network index.
 *
 * Security
 * --------
 * Who may see what is decided here, and the reasoning is not obvious, so:
 *
 * 1. **Current-site content** comes from `LiveSearch`, which runs under the
 *    requesting user's own session on their own blog. Correct by construction.
 *
 * 2. **Cross-network content is super admins only.** Whether a user may read a
 *    post on another site depends on that site's `map_meta_cap` /
 *    `user_has_cap` filters, which only exist while that site's plugins are
 *    loaded — and `switch_to_blog()` does not load another site's plugin set.
 *    A permission check made from here about another site is therefore
 *    structurally unreliable, and being wrong means leaking one tenant's
 *    content to another. Snapshotting capabilities at index time fails the
 *    same way with a delay: revoking access would leave titles visible until
 *    the next pass. So the gate is the capability, checked before the index is
 *    read at all.
 *
 * 3. **Sites** are filtered to the ones a non-super-admin actually belongs to,
 *    via `get_blogs_of_user()`.
 *
 * 4. **Users** require `manage_network_users`. Enumerating accounts across a
 *    network is a disclosure concern even when each name looks harmless.
 *
 * Do not "simplify" the super-admin gate in `network_results()`. It is the
 * whole boundary.
 *
 * @package ModernDashboard
 */

declare( strict_types = 1 );

namespace ModernDashboard\Palette;

use ModernDashboard\Data\MetricsRepository;
use ModernDashboard\Support\Capabilities;

defined( 'ABSPATH' ) || exit;

final class SearchController {

	/**
	 * Above this many sites, a whole-network scan costs more to read than the
	 * results are worth, so only site names stay searchable.
	 */
	public const SCAN_CEILING = 500;

	private SearchIndex $index;
	private LiveSearch $live;
	private MetricsRepository $repository;

	public function __construct( SearchIndex $index, LiveSearch $live, MetricsRepository $repository ) {
		$this->index      = $index;
		$this->live       = $live;
		$this->repository = $repository;
	}

	/**
	 * @param string   $term  Search term, already sanitized by the REST layer.
	 * @param string[] $types Result types to include.
	 * @param int      $limit Maximum results per source.
	 *
	 * @return array<string,mixed>
	 */
	public function search( string $term, array $types, int $limit ): array {
		$term = trim( $term );

		$live    = in_array( 'post', $types, true ) ? $this->live->query( $term, $limit ) : array();
		$sites   = in_array( 'site', $types, true ) ? $this->sites( $term, $limit ) : array();
		$network = $this->network_results( $term, $types, $limit );

		return array(
			'live'    => $live,
			'index'   => $network['results'],
			'sites'   => $sites,
			'stale'   => $network['stale'],
			'partial' => $network['partial'],
		);
	}

	/**
	 * Cross-network content and users.
	 *
	 * @param string   $term  Search term.
	 * @param string[] $types Result types to include.
	 * @param int      $limit Maximum results.
	 *
	 * @return array{results:array<int,array<string,mixed>>,stale:int|null,partial:bool}
	 */
	private function network_results( string $term, array $types, int $limit ): array {
		$empty = array(
			'results' => array(),
			'stale'   => null,
			'partial' => false,
		);

		// The gate. See the security note at the top of this file.
		if ( '' === $term || ! Capabilities::can_manage() ) {
			return $empty;
		}

		$site_ids = $this->repository->site_ids();

		if ( count( $site_ids ) > self::SCAN_CEILING ) {
			// Sites stay searchable; content does not. Reported honestly rather
			// than quietly returning less than the reader expects.
			return array(
				'results' => array(),
				'stale'   => null,
				'partial' => true,
			);
		}

		$wants_users = in_array( 'user', $types, true ) && current_user_can( 'manage_network_users' );
		$wants_posts = in_array( 'post', $types, true );

		if ( ! $wants_users && ! $wants_posts ) {
			return $empty;
		}

		$current = get_current_blog_id();
		$results = array();

		foreach ( $this->index->records( $site_ids ) as $blog_id => $record ) {
			foreach ( $record['entries'] ?? array() as $entry ) {
				$type = $entry['type'] ?? '';

				if ( 'user' === $type && ! $wants_users ) {
					continue;
				}

				if ( 'post' === $type && ! $wants_posts ) {
					continue;
				}

				// The live query already covers this site, and its results are
				// fresher and permission-checked per post.
				if ( 'post' === $type && (int) $blog_id === $current ) {
					continue;
				}

				if ( ! $this->matches( $term, (string) ( $entry['title'] ?? '' ) ) ) {
					continue;
				}

				$results[] = array_merge(
					$entry,
					array(
						'blogId'   => (int) $blog_id,
						'siteName' => (string) ( $record['name'] ?? '' ),
						'source'   => 'index',
					)
				);
			}
		}

		$results = array_slice( $results, 0, $limit );

		return array(
			'results' => $results,
			'stale'   => $this->index->oldest( array_column( $results, 'blogId' ) ),
			'partial' => false,
		);
	}

	/**
	 * Sites matching the term, restricted to what the reader may know about.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function sites( string $term, int $limit ): array {
		$allowed = $this->readable_site_ids();

		if ( array() === $allowed ) {
			return array();
		}

		$results = array();

		foreach ( $this->index->records( $allowed ) as $blog_id => $record ) {
			$name = (string) ( $record['name'] ?? '' );
			$url  = (string) ( $record['url'] ?? '' );

			if ( '' !== $term && ! $this->matches( $term, $name ) && ! $this->matches( $term, $url ) ) {
				continue;
			}

			$results[] = array(
				'type'   => 'site',
				'id'     => (int) $blog_id,
				'blogId' => (int) $blog_id,
				'title'  => $name,
				'sub'    => $url,
				'url'    => get_admin_url( (int) $blog_id ),
				'at'     => 0,
				'source' => 'index',
			);
		}

		return array_slice( $results, 0, $limit );
	}

	/**
	 * Sites whose existence the current user is entitled to know about.
	 *
	 * @return int[]
	 */
	private function readable_site_ids(): array {
		if ( Capabilities::can_manage() ) {
			return $this->repository->site_ids();
		}

		$blogs = get_blogs_of_user( get_current_user_id() );
		$ids   = array();

		foreach ( $blogs as $blog ) {
			$ids[] = (int) $blog->userblog_id;
		}

		// A member of an excluded site still should not see it here: exclusion
		// is a network-admin decision about what this plugin reports on.
		return array_values( array_intersect( $ids, $this->repository->site_ids() ) );
	}

	/**
	 * Case-insensitive substring test.
	 *
	 * Ranking is the client's job — this only decides what is worth sending.
	 */
	private function matches( string $term, string $haystack ): bool {
		if ( '' === $haystack ) {
			return false;
		}

		return false !== stripos( $haystack, $term );
	}
}
