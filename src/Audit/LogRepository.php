<?php
/**
 * Reads the audit log.
 *
 * Security
 * --------
 * An audit entry is a fact about a person: who did what, where, from which
 * address. That makes it more sensitive than the metrics this plugin otherwise
 * reports, and the boundary is correspondingly tighter.
 *
 * **Reading the log requires the network capability.** A site administrator is
 * not shown their own site's slice, because even that leaks more than it
 * looks: who logged in and when, which addresses they used, whose role was
 * changed by whom. The metrics side of this plugin can offer a site admin
 * their own numbers safely; an audit trail is a different kind of data, and
 * "you may see events on sites you administer" is a feature that deserves its
 * own deliberate design rather than being assumed here.
 *
 * One consequence is worth stating plainly, because it is easy to miss. The
 * search index deliberately stores only *published* content, on the grounds
 * that "drafts and private posts carry the highest disclosure risk and the
 * least search value". The audit log cannot make that trade — a deletion event
 * is only useful if it says *what* was deleted, and the thing deleted was
 * often never published. So this table holds unpublished titles that the
 * search index refuses to hold, which is precisely why its read gate is
 * tighter rather than looser. Any future per-site view must scrub
 * `object_name`, not merely filter by `blog_id`.
 *
 * Filtering happens in SQL rather than in PHP, unlike `MetricsRepository`. That
 * is not inconsistency — metrics are a bounded set of cached rows, a log is
 * unbounded and time-ordered, and pulling it into PHP to filter would defeat
 * the reason it has a table.
 *
 * @package ModernDashboard
 */

declare( strict_types = 1 );

namespace ModernDashboard\Audit;

use ModernDashboard\Settings\Settings;

defined( 'ABSPATH' ) || exit;

final class LogRepository {

	/** Hard ceiling on a page, whatever the request asks for. */
	private const MAX_PER_PAGE = 200;

	/**
	 * Prune on the collection batch rather than a cron of its own.
	 *
	 * The scheduler already runs on an interval and already holds a lock; a
	 * second timer to delete a few rows would be a moving part for nothing.
	 *
	 * @param Settings $settings Where the retention window is configured.
	 */
	public function register( Settings $settings ): void {
		add_action(
			'modern_dashboard_batch_complete',
			function () use ( $settings ): void {
				$this->prune( (int) $settings->get( 'audit_retention_days' ) );
			}
		);
	}

	/**
	 * Query the log.
	 *
	 * @param array<string,mixed> $args search, action, blog_id, user_id, since, until, page, per_page.
	 *
	 * @return array{items:array<int,array<string,mixed>>,total:int,page:int,per_page:int,pages:int}
	 */
	public function query( array $args = array() ): array {
		global $wpdb;

		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per_page = max( 1, min( self::MAX_PER_PAGE, (int) ( $args['per_page'] ?? 50 ) ) );

		if ( ! Schema::ready() ) {
			return $this->empty( $page, $per_page );
		}

		$where  = array( '1=1' );
		$params = array();

		if ( ! empty( $args['action'] ) ) {
			$where[]  = 'action = %s';
			// Dots are meaningful in an action name, so this cannot use
			// sanitize_key(); the value is bound, not interpolated.
			$params[] = preg_replace( '/[^a-z0-9._-]/', '', strtolower( (string) $args['action'] ) );
		}

		if ( ! empty( $args['group'] ) ) {
			// `post`, `user`, `plugin` … match the prefix of an action name.
			$where[]  = 'action LIKE %s';
			$params[] = $wpdb->esc_like( sanitize_key( (string) $args['group'] ) . '.' ) . '%';
		}

		if ( ! empty( $args['blog_id'] ) ) {
			$where[]  = 'blog_id = %d';
			$params[] = (int) $args['blog_id'];
		}

		if ( ! empty( $args['user_id'] ) ) {
			$where[]  = 'user_id = %d';
			$params[] = (int) $args['user_id'];
		}

		if ( ! empty( $args['since'] ) ) {
			$where[]  = 'recorded_at >= %d';
			$params[] = (int) $args['since'];
		}

		if ( ! empty( $args['until'] ) ) {
			$where[]  = 'recorded_at <= %d';
			$params[] = (int) $args['until'];
		}

		if ( ! empty( $args['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( (string) $args['search'] ) . '%';
			$where[]  = '( object_name LIKE %s OR user_login LIKE %s )';
			$params[] = $like;
			$params[] = $like;
		}

		$table  = Schema::table();
		$clause = implode( ' AND ', $where );
		$offset = ( $page - 1 ) * $per_page;

		// Both queries are assembled the same way: a table name derived from
		// $wpdb->base_prefix, a clause built only from literal fragments chosen
		// above, and every user value bound through prepare(). The placeholder
		// count is dynamic because the filter set is, which is why prepare() is
		// called with an argument array rather than a fixed signature.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total = (int) ( array() === $params
			? $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$clause}" )
			: $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$clause}", $params ) ) );

		$rows = (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE {$clause} ORDER BY recorded_at DESC, id DESC LIMIT %d OFFSET %d",
				array_merge( $params, array( $per_page, $offset ) )
			),
			ARRAY_A
		);
		// phpcs:enable

		return array(
			'items'    => array_map( array( $this, 'shape' ), $rows ),
			'total'    => $total,
			'page'     => $page,
			'per_page' => $per_page,
			'pages'    => (int) ceil( $total / $per_page ),
		);
	}

	/**
	 * Distinct actions present in the log, so the filter offers what exists
	 * rather than a hardcoded list that drifts from reality.
	 *
	 * @return string[]
	 */
	public function actions(): array {
		global $wpdb;

		if ( ! Schema::ready() ) {
			return array();
		}

		$table = Schema::table();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Table name from $wpdb->base_prefix; no user input in this query.
		return array_map( 'strval', (array) $wpdb->get_col( "SELECT DISTINCT action FROM {$table} ORDER BY action ASC" ) );
	}

	/**
	 * Delete entries older than the retention window.
	 *
	 * @param int $days Retention in days; 0 keeps everything.
	 *
	 * @return int Rows removed.
	 */
	public function prune( int $days ): int {
		global $wpdb;

		if ( $days < 1 || ! Schema::ready() ) {
			return 0;
		}

		$table  = Schema::table();
		$cutoff = time() - ( $days * DAY_IN_SECONDS );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Table name from $wpdb->base_prefix; the cutoff is bound.
		return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE recorded_at < %d", $cutoff ) );
	}

	public function total(): int {
		global $wpdb;

		if ( ! Schema::ready() ) {
			return 0;
		}

		$table = Schema::table();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Table name from $wpdb->base_prefix; no user input.
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}

	/**
	 * @param array<string,mixed> $row Raw database row.
	 *
	 * @return array<string,mixed>
	 */
	private function shape( array $row ): array {
		$site = get_site( (int) $row['blog_id'] );

		return array(
			'id'         => (int) $row['id'],
			'action'     => (string) $row['action'],
			'group'      => strtok( (string) $row['action'], '.' ),
			'blogId'     => (int) $row['blog_id'],
			'siteName'   => $site instanceof \WP_Site ? ( $site->blogname ?: $site->domain ) : '',
			'userId'     => (int) $row['user_id'],
			'userLogin'  => (string) $row['user_login'],
			'objectType' => (string) $row['object_type'],
			'objectId'   => (int) $row['object_id'],
			'objectName' => (string) $row['object_name'],
			'context'    => json_decode( (string) $row['context'], true ) ?: array(),
			'ip'         => (string) $row['ip'],
			'recordedAt' => (int) $row['recorded_at'],
		);
	}

	/**
	 * @return array{items:array<int,array<string,mixed>>,total:int,page:int,per_page:int,pages:int}
	 */
	private function empty( int $page, int $per_page ): array {
		return array(
			'items'    => array(),
			'total'    => 0,
			'page'     => $page,
			'per_page' => $per_page,
			'pages'    => 0,
		);
	}
}
