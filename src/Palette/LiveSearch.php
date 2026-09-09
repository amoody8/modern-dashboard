<?php
/**
 * Searches the current site, live.
 *
 * It runs on the blog the user is already on, under their own session, with no
 * `switch_to_blog()` — so the capability checks are the ones WordPress would
 * make anywhere else, including any `map_meta_cap` filter the site's own
 * plugins registered. Nothing is cached and nothing can be stale.
 *
 * Unpublished posts need care. `'perm' => 'readable'` is narrower than it
 * sounds: it constrains only the `private` status, and leaves `draft`,
 * `pending` and `future` unrestricted by author. So rather than lean on it,
 * every result is checked with `current_user_can( 'edit_post' )` before it is
 * returned — a title alone is enough to leak what a colleague is working on.
 *
 * A site running a search plugin (Relevanssi, ElasticPress) accelerates this
 * for free: `WP_Query` runs their `posts_search` filters like any other query.
 *
 * @package ModernDashboard
 */

declare( strict_types = 1 );

namespace ModernDashboard\Palette;

use WP_Query;

defined( 'ABSPATH' ) || exit;

final class LiveSearch {

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public function query( string $term, int $limit = 8 ): array {
		$term = trim( $term );

		if ( '' === $term ) {
			return array();
		}

		$query = new WP_Query(
			array(
				's'                      => $term,
				'post_type'              => $this->post_types(),
				'post_status'            => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				// Over-fetch: unpublished rows the user may not see are dropped
				// below, and taking exactly $limit here would let them displace
				// results the user is entitled to.
				'posts_per_page'         => $limit * 3,
				'orderby'                => 'relevance',
				'no_found_rows'          => true,
				'ignore_sticky_posts'    => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				// Kept for the private-post clauses it does add; the checks in
				// the loop below are what actually decide what is returned.
				'perm'                   => 'readable',
			)
		);

		$blog_id = get_current_blog_id();
		$results = array();

		foreach ( $query->posts as $post ) {
			$editable = current_user_can( 'edit_post', $post->ID );

			// Anything not yet public is editors-only: `read_post` is too weak a
			// test here, because on many configurations it passes for a user who
			// cannot edit the post and would still learn its title.
			if ( 'publish' !== $post->post_status ) {
				if ( ! $editable ) {
					continue;
				}
			} elseif ( ! $editable && ! current_user_can( 'read_post', $post->ID ) ) {
				continue;
			}

			if ( count( $results ) >= $limit ) {
				break;
			}

			$results[] = array(
				'type'   => 'post',
				'id'     => (int) $post->ID,
				'blogId' => $blog_id,
				'title'  => (string) $post->post_title,
				'sub'    => (string) $post->post_type,
				'status' => (string) $post->post_status,
				'url'    => $editable
					? (string) get_edit_post_link( $post->ID, 'raw' )
					: (string) get_permalink( $post->ID ),
				'at'     => (int) strtotime( $post->post_modified_gmt . ' UTC' ),
				'source' => 'live',
			);
		}

		return $results;
	}

	/**
	 * @return string[]
	 */
	private function post_types(): array {
		$types = get_post_types( array( 'show_ui' => true ), 'names' );

		unset( $types['attachment'] );

		/** This filter is documented in src/Palette/IndexBuilder.php */
		return array_values( (array) apply_filters( 'modern_dashboard_palette_post_types', array_values( $types ) ) );
	}
}
