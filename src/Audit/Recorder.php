<?php
/**
 * Writes audit entries.
 *
 * Events happen inside a site — someone publishes a post on site 7 — but the
 * log is network-level. That works because the table lives on the base prefix
 * and is addressed directly, so no `switch_to_blog()` is involved: the row
 * simply records which blog it came from.
 *
 * Everything here is best-effort. A log that throws, or that blocks the action
 * it is describing, is worse than a log with a gap in it — so a failed insert
 * is swallowed and the user's action completes regardless.
 *
 * @package ModernDashboard
 */

declare( strict_types = 1 );

namespace ModernDashboard\Audit;

use ModernDashboard\Settings\Settings;

defined( 'ABSPATH' ) || exit;

final class Recorder {

	/** Object names longer than this are truncated before storage. */
	private const MAX_NAME = 300;

	private Settings $settings;

	private ?bool $enabled = null;

	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Record one event.
	 *
	 * @param string              $action      Machine name, e.g. `post.published`.
	 * @param array<string,mixed> $entry       Object details.
	 * @param int|null            $blog_id     Site the event belongs to; current site by default.
	 */
	public function record( string $action, array $entry = array(), ?int $blog_id = null ): void {
		if ( ! $this->enabled() ) {
			return;
		}

		global $wpdb;

		$user = wp_get_current_user();

		$row = array(
			'blog_id'     => (int) ( $blog_id ?? get_current_blog_id() ),
			'user_id'     => (int) ( $user->ID ?? 0 ),
			'user_login'  => (string) ( $user->user_login ?? '' ),
			'action'      => $this->action_name( $action ),
			'object_type' => substr( sanitize_key( (string) ( $entry['object_type'] ?? '' ) ), 0, 32 ),
			'object_id'   => (int) ( $entry['object_id'] ?? 0 ),
			'object_name' => mb_substr( (string) ( $entry['object_name'] ?? '' ), 0, self::MAX_NAME ),
			'context'     => (string) wp_json_encode( $this->context( $entry ) ),
			'ip'          => $this->ip(),
			'recorded_at' => time(),
		);

		try {
			if ( ! Schema::ready() ) {
				return;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- This is the plugin's own table; there is no API for it and no cache to invalidate.
			$wpdb->insert( Schema::table(), $row );
		} catch ( \Throwable $e ) {
			// Deliberately swallowed. The log describes an action; it must never
			// be the reason that action fails.
			return;
		}
	}

	/**
	 * Normalize an action name.
	 *
	 * Not `sanitize_key()`: that strips the dot, and the dot is load-bearing —
	 * `post.published` groups under `post`, and the log's group filter matches
	 * on the prefix. Running it through sanitize_key() silently produced
	 * `postpublished`, which broke grouping without erroring anywhere.
	 */
	private function action_name( string $action ): string {
		$clean = strtolower( preg_replace( '/[^a-zA-Z0-9._-]/', '', $action ) ?? '' );

		return substr( $clean, 0, 64 );
	}

	/**
	 * Extra detail, kept as JSON so an event type can carry whatever it needs
	 * without a schema change.
	 *
	 * @param array<string,mixed> $entry Raw entry.
	 *
	 * @return array<string,mixed>
	 */
	private function context( array $entry ): array {
		$context = $entry['context'] ?? array();

		if ( ! is_array( $context ) ) {
			return array();
		}

		// Bounded so a caller cannot put an unbounded payload in a log row.
		return array_slice( array_map( array( $this, 'scalar' ), $context ), 0, 12, true );
	}

	/**
	 * @param mixed $value Raw value.
	 *
	 * @return string|int|float|bool|null
	 */
	private function scalar( $value ) {
		if ( is_scalar( $value ) || null === $value ) {
			return is_string( $value ) ? mb_substr( $value, 0, 200 ) : $value;
		}

		return null;
	}

	/**
	 * The client address, when it can be trusted.
	 *
	 * Only REMOTE_ADDR is read. Forwarded-for headers are attacker-controlled
	 * unless a proxy is known to rewrite them, and an audit log that records a
	 * spoofable address as fact is worse than one that records none.
	 */
	private function ip(): string {
		$remote = isset( $_SERVER['REMOTE_ADDR'] )
			? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) )
			: '';

		$valid = filter_var( $remote, FILTER_VALIDATE_IP );

		return is_string( $valid ) ? $valid : '';
	}

	private function enabled(): bool {
		if ( null === $this->enabled ) {
			$this->enabled = (bool) $this->settings->get( 'audit_enabled' );
		}

		return $this->enabled;
	}
}
