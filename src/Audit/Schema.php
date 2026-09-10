<?php
/**
 * The audit log's table.
 *
 * This is the plugin's first custom table, and the reason is a real change of
 * shape rather than a preference. Every other store here holds a *bounded,
 * per-site record*: metrics are one row per site, the search index caps at 60
 * entries per site. An audit log is none of those things — it is append-only,
 * unbounded, time-ordered, and queried across every site at once.
 *
 * Holding that in a network option would mean reading and rewriting an
 * ever-growing serialized blob on every single event, and answering "what
 * happened last Tuesday on site 12" by unserializing the whole history in PHP.
 * The README already names the threshold where the option pattern stops paying
 * ("past that the index should move to a custom table so the database does the
 * sorting"); a log crosses it on day one.
 *
 * @package ModernDashboard
 */

declare( strict_types = 1 );

namespace ModernDashboard\Audit;

defined( 'ABSPATH' ) || exit;

final class Schema {

	/** Bumped when the table definition changes, to trigger dbDelta. */
	public const VERSION = 1;

	/** Where the installed version is recorded. */
	public const VERSION_OPTION = 'modern_dashboard_audit_schema';

	/**
	 * Fully-qualified table name.
	 *
	 * One table for the whole network, on the base prefix — the log is
	 * network-level data even though every row is written from inside a site.
	 */
	public static function table(): string {
		global $wpdb;

		return $wpdb->base_prefix . 'modern_dashboard_audit';
	}

	/**
	 * Create or update the table.
	 *
	 * Safe to call repeatedly: dbDelta only issues statements when the live
	 * schema differs, and the version check means the common case does not even
	 * reach it.
	 */
	public static function install(): void {
		if ( (int) get_network_option( null, self::VERSION_OPTION, 0 ) === self::VERSION ) {
			return;
		}

		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table();
		$charset = $wpdb->get_charset_collate();

		// Index rationale, since these are the queries the log actually serves:
		//
		// recorded_at — every listing is time-ordered, newest first.
		// blog_id — "what happened on this site".
		// user_id — "what did this person do".
		// action — "show me plugin activations".
		//
		// object_id is deliberately unindexed: it is shown, not searched, and
		// an index on it would cost writes for a query nobody makes.
		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			blog_id bigint(20) unsigned NOT NULL DEFAULT 0,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			user_login varchar(60) NOT NULL DEFAULT '',
			action varchar(64) NOT NULL DEFAULT '',
			object_type varchar(32) NOT NULL DEFAULT '',
			object_id bigint(20) unsigned NOT NULL DEFAULT 0,
			object_name text NOT NULL,
			context longtext NOT NULL,
			ip varchar(45) NOT NULL DEFAULT '',
			recorded_at bigint(20) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY recorded_at (recorded_at),
			KEY blog_id (blog_id),
			KEY user_id (user_id),
			KEY action (action)
		) {$charset};";

		dbDelta( $sql );

		update_network_option( null, self::VERSION_OPTION, self::VERSION );
	}

	/**
	 * Remove the table. Called from uninstall, never from deactivation —
	 * deactivating a plugin should not destroy an audit trail.
	 */
	public static function drop(): void {
		global $wpdb;

		$table = self::table();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Table names cannot be parameterized, and this name is built from $wpdb->base_prefix, not from input.
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );

		delete_network_option( null, self::VERSION_OPTION );
	}

	/**
	 * Whether the table exists.
	 *
	 * Read before every write path so a missing table degrades to "no logging"
	 * rather than a fatal on a network where activation somehow did not run.
	 */
	public static function ready(): bool {
		global $wpdb;

		$table = self::table();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- SHOW TABLES cannot use a placeholder for the name; the value is not user input.
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
	}
}
