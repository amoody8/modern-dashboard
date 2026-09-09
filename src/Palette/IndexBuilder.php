<?php
/**
 * Fills the search index, one batch of sites at a time.
 *
 * Rides `modern_dashboard_batch_complete` rather than scheduling its own cron:
 * that hook fires with exactly the sites the metrics batch just refreshed,
 * already inside the scheduler's lock, so the index inherits the same
 * round-robin, the same lock, and the same "a big network just takes more
 * passes" property without a second moving part.
 *
 * @package ModernDashboard
 */

declare( strict_types = 1 );

namespace ModernDashboard\Palette;

use ModernDashboard\Settings\Settings;

defined( 'ABSPATH' ) || exit;

final class IndexBuilder {

	/** Published posts kept per site. */
	private const POST_LIMIT = 40;

	/** Users kept per site. */
	private const USER_LIMIT = 20;

	private SearchIndex $index;
	private Settings $settings;

	public function __construct( SearchIndex $index, Settings $settings ) {
		$this->index    = $index;
		$this->settings = $settings;
	}

	public function register(): void {
		add_action( 'modern_dashboard_batch_complete', array( $this, 'on_batch_complete' ) );
		add_action( 'wp_delete_site', array( $this, 'on_site_deleted' ) );
	}

	/**
	 * The IDs arrive from `MetricsRepository::refresh_batch()`, which already
	 * filtered them through `site_ids()` — so excluded sites never reach the
	 * index. Worth knowing before moving this hook somewhere else.
	 *
	 * @param int[] $refreshed Blog IDs the metrics batch just refreshed.
	 */
	public function on_batch_complete( array $refreshed ): void {
		if ( ! $this->settings->get( 'palette_enabled' ) ) {
			return;
		}

		foreach ( $refreshed as $blog_id ) {
			try {
				$this->refresh( (int) $blog_id );
			} catch ( \Throwable $e ) {
				// This runs inside the scheduler's lock. One unindexable site
				// must not cost the rest of the batch, nor hold the lock while
				// an exception unwinds.
				continue;
			}
		}
	}

	/**
	 * @param \WP_Site|int $site Site being deleted.
	 */
	public function on_site_deleted( $site ): void {
		$blog_id = is_object( $site ) ? (int) $site->blog_id : (int) $site;

		$this->index->delete( $blog_id );
	}

	/**
	 * Rebuild one site's entries, writing only when they actually changed.
	 */
	public function refresh( int $blog_id ): void {
		$site = get_site( $blog_id );

		if ( ! $site instanceof \WP_Site ) {
			return;
		}

		$entries  = $this->build( $blog_id );
		$existing = $this->index->get( $blog_id );

		// Indexing runs on every batch; rewriting an unchanged row would be a
		// query per site per pass for no gain.
		if ( null !== $existing && $this->signature( $existing['entries'] ?? array() ) === $this->signature( $entries ) ) {
			return;
		}

		$this->index->set(
			$blog_id,
			array(
				'blog_id'    => (int) $site->blog_id,
				'name'       => $site->blogname ?: $site->domain,
				'url'        => untrailingslashit( $site->siteurl ?: ( 'https://' . $site->domain . $site->path ) ),
				'indexed_at' => time(),
				'entries'    => $entries,
			)
		);
	}

	/**
	 * Collect one site's searchable entries.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function build( int $blog_id ): array {
		switch_to_blog( $blog_id );

		try {
			$entries = array_merge( $this->posts(), $this->users() );
		} finally {
			restore_current_blog();
		}

		return array_slice( $entries, 0, SearchIndex::MAX_ENTRIES );
	}

	/**
	 * Only published content is indexed. Drafts and private posts carry the
	 * highest disclosure risk and the least search value, and a network-level
	 * index is the wrong place to reason about either.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function posts(): array {
		$posts = get_posts(
			array(
				'post_type'        => $this->post_types(),
				'post_status'      => 'publish',
				'numberposts'      => self::POST_LIMIT,
				'orderby'          => 'modified',
				'order'            => 'DESC',
				'suppress_filters' => true,
				'no_found_rows'    => true,
			)
		);

		$entries = array();

		foreach ( $posts as $post ) {
			$entries[] = array(
				'type'  => 'post',
				'id'    => (int) $post->ID,
				'title' => (string) $post->post_title,
				'sub'   => (string) $post->post_type,
				'url'   => get_edit_post_link( $post->ID, 'raw' ),
				'at'    => (int) strtotime( $post->post_modified_gmt . ' UTC' ),
			);
		}

		return $entries;
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	private function users(): array {
		$users = get_users(
			array(
				'number'  => self::USER_LIMIT,
				'orderby' => 'registered',
				'order'   => 'DESC',
				'fields'  => array( 'ID', 'display_name', 'user_login' ),
			)
		);

		$entries = array();

		foreach ( $users as $user ) {
			$entries[] = array(
				'type'  => 'user',
				'id'    => (int) $user->ID,
				'title' => (string) ( $user->display_name ?: $user->user_login ),
				'sub'   => (string) $user->user_login,
				'url'   => get_edit_user_link( $user->ID ),
				'at'    => 0,
			);
		}

		return $entries;
	}

	/**
	 * @return string[]
	 */
	private function post_types(): array {
		$types = get_post_types(
			array(
				'show_ui' => true,
				'public'  => true,
			),
			'names'
		);

		unset( $types['attachment'] );

		/**
		 * Filters the post types written into the search index.
		 *
		 * @param string[] $types Post type names.
		 */
		return array_values( (array) apply_filters( 'modern_dashboard_palette_post_types', array_values( $types ) ) );
	}

	/**
	 * Fingerprint of a site's entries, for deciding whether a write is needed.
	 *
	 * Deliberately excludes `at`: modification times move constantly and would
	 * defeat the guard, the same way `seen` would in `MenuCatalogue`. Sorting
	 * the reduced shape makes the signature order-independent, so a reordered
	 * result set is not mistaken for a changed one.
	 *
	 * @param array<int,array<string,mixed>> $entries Entries to fingerprint.
	 */
	private function signature( array $entries ): string {
		$reduced = array();

		foreach ( $entries as $entry ) {
			$reduced[] = array(
				$entry['type'] ?? '',
				$entry['id'] ?? 0,
				$entry['title'] ?? '',
				$entry['url'] ?? '',
			);
		}

		sort( $reduced );

		return md5( (string) wp_json_encode( $reduced ) );
	}
}
