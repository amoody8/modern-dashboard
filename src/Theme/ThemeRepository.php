<?php
/**
 * Where themes live and how one is chosen for a site.
 *
 * The network default is a network option. A per-site override is a blog option
 * on that site, so the site's own admin requests read it without a
 * `switch_to_blog()` — branding is on the hot path of every admin page load and
 * must not cost a context switch.
 *
 * An override **replaces** the network theme rather than merging into it. A
 * merging model needs a per-field "is this overridden?" flag, and the resulting
 * half-inherited themes are far harder to reason about than "this site has its
 * own branding".
 *
 * @package ModernDashboard
 */

declare( strict_types = 1 );

namespace ModernDashboard\Theme;

defined( 'ABSPATH' ) || exit;

final class ThemeRepository {

	public const NETWORK_OPTION = 'modern_dashboard_theme';
	public const SITE_OPTION    = 'modern_dashboard_theme_override';

	/** @var array<string,mixed>|null */
	private ?array $network_cache = null;

	/** @var array<int,array<string,mixed>> */
	private array $site_cache = array();

	/**
	 * The network-wide theme.
	 *
	 * @return array<string,mixed>
	 */
	public function network(): array {
		if ( null === $this->network_cache ) {
			$stored = get_network_option( null, self::NETWORK_OPTION, array() );

			$this->network_cache = Theme::sanitize( is_array( $stored ) ? $stored : array() );
		}

		return $this->network_cache;
	}

	/**
	 * @param array<string,mixed> $input Untrusted theme.
	 *
	 * @return array<string,mixed>
	 */
	public function save_network( array $input ): array {
		$clean = Theme::sanitize( $input );

		update_network_option( null, self::NETWORK_OPTION, $clean );
		$this->network_cache = $clean;

		return $clean;
	}

	/**
	 * A site's override, or null when it has none.
	 *
	 * @return array<string,mixed>|null
	 */
	public function site_override( ?int $blog_id = null ): ?array {
		$blog_id = $blog_id ?? get_current_blog_id();

		if ( array_key_exists( $blog_id, $this->site_cache ) ) {
			return $this->site_cache[ $blog_id ] ?: null;
		}

		$stored = $this->read_site_option( $blog_id );
		$clean  = is_array( $stored ) && array() !== $stored ? Theme::sanitize( $stored ) : null;

		$this->site_cache[ $blog_id ] = $clean ?? array();

		return $clean;
	}

	/**
	 * @param int                 $blog_id Site to brand.
	 * @param array<string,mixed> $input   Untrusted theme.
	 *
	 * @return array<string,mixed>
	 */
	public function save_site( int $blog_id, array $input ): array {
		$clean = Theme::sanitize( $input );

		$this->write_site_option( $blog_id, $clean );
		$this->site_cache[ $blog_id ] = $clean;

		return $clean;
	}

	public function clear_site( int $blog_id ): void {
		$this->write_site_option( $blog_id, null );
		$this->site_cache[ $blog_id ] = array();
	}

	/**
	 * The theme that should actually render on a site, or null when branding is
	 * switched off for it.
	 *
	 * @return array<string,mixed>|null
	 */
	public function resolve( ?int $blog_id = null ): ?array {
		$override = $this->site_override( $blog_id );

		if ( null !== $override ) {
			return $override['enabled'] ? $override : null;
		}

		$network = $this->network();

		return $network['enabled'] ? $network : null;
	}

	/**
	 * @return array<string,mixed>|null
	 */
	private function read_site_option( int $blog_id ): ?array {
		$switched = false;

		if ( get_current_blog_id() !== $blog_id ) {
			switch_to_blog( $blog_id );
			$switched = true;
		}

		$stored = get_option( self::SITE_OPTION, null );

		if ( $switched ) {
			restore_current_blog();
		}

		return is_array( $stored ) ? $stored : null;
	}

	/**
	 * @param int                      $blog_id Site to write to.
	 * @param array<string,mixed>|null $value   Theme to write, or null to remove.
	 */
	private function write_site_option( int $blog_id, ?array $value ): void {
		$switched = false;

		if ( get_current_blog_id() !== $blog_id ) {
			switch_to_blog( $blog_id );
			$switched = true;
		}

		if ( null === $value ) {
			delete_option( self::SITE_OPTION );
		} else {
			update_option( self::SITE_OPTION, $value );
		}

		if ( $switched ) {
			restore_current_blog();
		}
	}
}
