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
	 * Signature matches Scheduler::on_site_deleted(), which handles the same
	 * hook — `wp_delete_site` passes a WP_Site.
	 *
	 * @param \WP_Site|int $site Site being deleted.
	 */
	public function on_site_deleted( $site ): void {
		if ( $site instanceof \WP_Site ) {
			$this->index->delete( (int) $site->blog_id );
		}
	}

	/**
	 * Rebuild one site's entries, writing only when they actually changed.
	 */
	public function refresh( int $blog_id ): void {
		$site = get_site( $blog_id );

		if ( ! $site instanceof \WP_Site ) {
			return;
		}

		$existing = $this->index->get( $blog_id );

		// This runs inside the scheduler's lock, on top of metrics collection
		// that already visited the site. `last_updated` moves whenever the site
		// publishes, so a site that has not changed since the last pass can be
		// skipped before paying for the queries — the signature guard below can
		// only save the write, not the read.
		if ( null !== $existing && ! $this->changed_since( $site, (int) ( $existing['indexed_at'] ?? 0 ) ) ) {
			return;
		}

		$entries = $this->build( $blog_id );

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
	 * Whether a site has published anything since it was last indexed.
	 *
	 * Users are not covered by `last_updated`, so this is a heuristic: a site
	 * whose only change is a new account waits for the next content change or
	 * for a manual refresh. That is the right trade for a search index that is
	 * already eventually consistent.
	 */
	private function changed_since( \WP_Site $site, int $indexed_at ): bool {
		if ( 0 === $indexed_at ) {
			return true;
		}

		$updated = strtotime( (string) $site->last_updated . ' UTC' );

		return false === $updated || $updated >= $indexed_at;
	}

	/**
	 * Collect one site's searchable entries.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function build( int $blog_id ): array {
		// Runs switched so admin_url() resolves against this site — that is what
		// makes a stored URL point at the site that owns the row.
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
				'url'   => $this->edit_post_url( $post ),
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
				'url'   => admin_url( 'user-edit.php?user_id=' . (int) $user->ID ),
				'at'    => 0,
			);
		}

		return $entries;
	}

	/**
	 * Editor URL for a post.
	 *
	 * Deliberately not `get_edit_post_link()`: that returns null unless the
	 * *current* user can edit the post, and indexing runs on cron where there is
	 * no current user, so every URL would come back empty. Permission is not
	 * this function's business anyway — the index is only ever read by someone
	 * who has already cleared the network capability, and the destination
	 * enforces its own access when they arrive.
	 *
	 * @param \WP_Post $post Post being indexed.
	 */
	private function edit_post_url( \WP_Post $post ): string {
		$type = get_post_type_object( $post->post_type );

		// Custom post types can declare their own edit link template.
		if ( $type && ! empty( $type->_edit_link ) ) {
			return admin_url( sprintf( $type->_edit_link . '&action=edit', $post->ID ) );
		}

		return admin_url( 'post.php?post=' . (int) $post->ID . '&action=edit' );
	}

	/**
	 * @return string[]
	 */
	private function post_types(): array {
		// Must match LiveSearch: the two halves feed one result list, and a type
		// searchable on the current site but missing from the index would make
		// the same query answer differently depending on where it was run.
		$types = get_post_types( array( 'show_ui' => true ), 'names' );

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
