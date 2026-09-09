<?php
/**
 * Applies runtime context to the command catalogue.
 *
 * Separate from the registry for the same reason `LayoutSanitizer` is separate
 * from `BlockRegistry`: the registry declares what exists, the resolver decides
 * what this user, on this screen, is allowed to see.
 *
 * @package ModernDashboard
 */

declare( strict_types = 1 );

namespace ModernDashboard\Palette;

defined( 'ABSPATH' ) || exit;

final class CommandResolver {

	private CommandRegistry $registry;

	public function __construct( CommandRegistry $registry ) {
		$this->registry = $registry;
	}

	/**
	 * Commands the current user may run on the current screen, ordered.
	 *
	 * Capability filtering happens here rather than in the browser: a command a
	 * user cannot run is never serialized, so the palette cannot leak the
	 * existence of screens they have no access to.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function for_current_user(): array {
		$context  = is_network_admin() ? 'network' : 'site';
		$resolved = array();

		foreach ( $this->registry->all() as $id => $definition ) {
			if ( ! $this->permitted( $definition, $context ) ) {
				continue;
			}

			$resolved[] = $this->to_rest( $id, $definition );
		}

		usort(
			$resolved,
			static function ( array $a, array $b ): int {
				return array( $a['priority'], $a['label'] ) <=> array( $b['priority'], $b['label'] );
			}
		);

		return $resolved;
	}

	/**
	 * One command, re-authorized.
	 *
	 * The browser sends back an id; nothing about that id is trusted. Anything
	 * acting on a command must come through here rather than reading the
	 * registry directly.
	 *
	 * @return array<string,mixed>|null Null when unknown or not permitted.
	 */
	public function resolve( string $id ): ?array {
		$definition = $this->registry->get( $id );

		if ( null === $definition ) {
			return null;
		}

		$context = is_network_admin() ? 'network' : 'site';

		if ( ! $this->permitted( $definition, $context ) ) {
			return null;
		}

		return $this->to_rest( $id, $definition );
	}

	/**
	 * @param array<string,mixed> $definition Normalized definition.
	 * @param string              $context    'network' or 'site'.
	 */
	private function permitted( array $definition, string $context ): bool {
		$wanted = (string) $definition['context'];

		if ( 'any' !== $wanted && $wanted !== $context ) {
			return false;
		}

		return current_user_can( (string) $definition['capability'] );
	}

	/**
	 * @param array<string,mixed> $definition Normalized definition.
	 *
	 * @return array<string,mixed>
	 */
	private function to_rest( string $id, array $definition ): array {
		$definition['id']     = $id;
		$definition['action'] = $this->resolve_action( $definition['action'] );

		// The capability has done its work server-side; sending it would only
		// invite the client to re-implement the check badly.
		unset( $definition['capability'] );

		return $definition;
	}

	/**
	 * @param array<string,mixed> $action Raw action.
	 *
	 * @return array<string,mixed>
	 */
	private function resolve_action( array $action ): array {
		if ( isset( $action['url'] ) ) {
			$action['url'] = $this->resolve_url( (string) $action['url'] );
		}

		return $action;
	}

	/**
	 * Expand the prefixes the registry stores instead of literal URLs.
	 *
	 * The indirection keeps the registry pure data — no URL functions run while
	 * building a catalogue that may be filtered away a moment later, and a
	 * per-site destination can be resolved against the right blog.
	 */
	private function resolve_url( string $value ): string {
		if ( str_starts_with( $value, 'network_admin_url:' ) ) {
			return network_admin_url( substr( $value, 18 ) );
		}

		if ( str_starts_with( $value, 'admin_url:' ) ) {
			return admin_url( substr( $value, 10 ) );
		}

		if ( preg_match( '/^site_admin_url:(\d+):(.*)$/', $value, $matches ) ) {
			return get_admin_url( (int) $matches[1], $matches[2] );
		}

		return esc_url_raw( $value );
	}
}
