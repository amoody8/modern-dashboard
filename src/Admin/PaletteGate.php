<?php
/**
 * Decides whether the palette loads on this request.
 *
 * The palette is the only part of this plugin that ships JavaScript to every
 * admin screen of every site, so the decision is concentrated here and answered
 * once per request rather than rediscovered by each caller.
 *
 * `?mdash-palette=off` suppresses it for one page load, matching the recovery
 * hatch the menu and branding subsystems already offer.
 *
 * @package ModernDashboard
 */

declare( strict_types = 1 );

namespace ModernDashboard\Admin;

use ModernDashboard\Settings\Settings;
use ModernDashboard\Support\Capabilities;

defined( 'ABSPATH' ) || exit;

final class PaletteGate {

	/** Query argument that suppresses the palette for one request. */
	public const BYPASS_ARG = 'mdash-palette';

	private Settings $settings;

	private ?bool $resolved = null;

	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	public function register(): void {
		add_action( 'admin_notices', array( $this, 'render_bypass_notice' ) );
		add_action( 'network_admin_notices', array( $this, 'render_bypass_notice' ) );
	}

	public function should_load(): bool {
		if ( null !== $this->resolved ) {
			return $this->resolved;
		}

		$this->resolved = $this->decide();

		return $this->resolved;
	}

	public function bypassed(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display toggle for one request; it changes nothing.
		$requested = isset( $_GET[ self::BYPASS_ARG ] ) ? sanitize_key( wp_unslash( (string) $_GET[ self::BYPASS_ARG ] ) ) : '';

		return 'off' === $requested && current_user_can( 'manage_options' );
	}

	public function render_bypass_notice(): void {
		if ( ! $this->bypassed() ) {
			return;
		}

		echo '<div class="notice notice-info"><p>';
		esc_html_e(
			'The command palette is switched off for this page load. Reload without the mdash-palette query argument to bring it back.',
			'modern-dashboard'
		);
		echo '</p></div>';
	}

	private function decide(): bool {
		if ( ! is_multisite() || ! is_user_logged_in() ) {
			return false;
		}

		// The user-admin context has no site to act on, so nothing in the
		// catalogue would apply there.
		if ( is_user_admin() ) {
			return false;
		}

		if ( ! $this->settings->get( 'palette_enabled' ) ) {
			return false;
		}

		if ( $this->bypassed() ) {
			return false;
		}

		return current_user_can( Capabilities::VIEW ) || current_user_can( Capabilities::MANAGE );
	}
}
