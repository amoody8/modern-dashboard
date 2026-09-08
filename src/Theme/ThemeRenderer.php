<?php
/**
 * Puts the theme on screen.
 *
 * Colours are re-validated here rather than trusted from storage. They were
 * sanitized on the way in, but this is the point where a string becomes CSS, and
 * a stored option can be edited by other means — WP-CLI, a migration, a plugin.
 * Validating at the boundary that matters costs nothing.
 *
 * `?mdash-theme=off` renders the untouched admin for anyone who can
 * `manage_options`, so a colour scheme nobody can read is always recoverable.
 *
 * @package ModernDashboard
 */

declare( strict_types = 1 );

namespace ModernDashboard\Theme;

defined( 'ABSPATH' ) || exit;

final class ThemeRenderer {

	/** Query argument that disables branding for one request. */
	public const BYPASS_ARG = 'mdash-theme';

	private ThemeRepository $themes;

	/** @var array<string,mixed>|null */
	private ?array $resolved = null;

	private bool $resolved_once = false;

	public function __construct( ThemeRepository $themes ) {
		$this->themes = $themes;
	}

	public function register(): void {
		// Late in the head, so it lands after the core admin colour scheme.
		add_action( 'admin_head', array( $this, 'render_admin_css' ), 100 );
		add_action( 'login_head', array( $this, 'render_login_css' ), 100 );

		add_filter( 'login_headerurl', array( $this, 'login_url' ) );
		add_filter( 'login_headertext', array( $this, 'login_title' ) );
		add_filter( 'admin_footer_text', array( $this, 'footer_text' ) );
		add_action( 'admin_bar_menu', array( $this, 'adjust_admin_bar' ), 11 );
		add_action( 'admin_notices', array( $this, 'render_bypass_notice' ) );
	}

	public function render_admin_css(): void {
		$theme = $this->theme();

		if ( null === $theme ) {
			return;
		}

		$css = $this->admin_css( $theme );

		printf( "<style id='modern-dashboard-branding'>%s</style>\n", $css ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from hex colours and integers validated in admin_css(); escaping would corrupt the CSS.
	}

	public function render_login_css(): void {
		$theme = $this->theme();

		if ( null === $theme ) {
			return;
		}

		$css = $this->login_css( $theme );

		printf( "<style id='modern-dashboard-login'>%s</style>\n", $css ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- As above: hex colours, integers and one URL through css_url().
	}

	/**
	 * @param string $url Default login logo URL.
	 */
	public function login_url( $url ): string {
		$theme = $this->theme();

		return $theme && '' !== $theme['login_url'] ? $theme['login_url'] : (string) $url;
	}

	/**
	 * @param string $text Default login logo text.
	 */
	public function login_title( $text ): string {
		$theme = $this->theme();

		return $theme && '' !== $theme['login_title'] ? $theme['login_title'] : (string) $text;
	}

	/**
	 * @param string $text Default admin footer text.
	 */
	public function footer_text( $text ): string {
		$theme = $this->theme();

		return $theme && '' !== $theme['footer_text'] ? esc_html( $theme['footer_text'] ) : (string) $text;
	}

	/**
	 * @param \WP_Admin_Bar $bar The admin bar.
	 */
	public function adjust_admin_bar( $bar ): void {
		$theme = $this->theme();

		if ( null === $theme || ! $bar instanceof \WP_Admin_Bar ) {
			return;
		}

		if ( $theme['hide_wp_logo'] ) {
			$bar->remove_node( 'wp-logo' );
		}

		if ( '' === $theme['howdy_text'] ) {
			return;
		}

		$account = $bar->get_node( 'my-account' );

		if ( ! $account ) {
			return;
		}

		// Core builds this title as "Howdy, %s". Swapping just the greeting keeps
		// the display name and avatar markup core produced.
		$bar->add_node(
			array(
				'id'    => 'my-account',
				// phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Deliberately core's own string: the greeting must match whatever WordPress rendered in the user's locale for the replacement to find it.
				'title' => str_replace( __( 'Howdy,', 'default' ), esc_html( $theme['howdy_text'] ), $account->title ),
			)
		);
	}

	public function render_bypass_notice(): void {
		if ( ! $this->bypassed() ) {
			return;
		}

		echo '<div class="notice notice-info"><p>';
		esc_html_e(
			'Showing the unbranded admin. Reload without the mdash-theme query argument to see your branding again.',
			'modern-dashboard'
		);
		echo '</p></div>';
	}

	/**
	 * @param array<string,mixed> $theme Resolved theme.
	 */
	private function admin_css( array $theme ): string {
		$colour = static fn( string $key, string $fallback ): string => Theme::colour( $theme[ $key ] ?? null, $fallback );
		$radius = max( 0, min( 24, (int) ( $theme['radius'] ?? 4 ) ) );

		$menu_bg     = $colour( 'menu_bg', '#1d2327' );
		$menu_text   = $colour( 'menu_text', '#f0f0f1' );
		$active_bg   = $colour( 'menu_active_bg', '#2271b1' );
		$active_text = $colour( 'menu_active_text', '#ffffff' );
		$bar_bg      = $colour( 'bar_bg', '#1d2327' );
		$bar_text    = $colour( 'bar_text', '#f0f0f1' );
		$accent      = $colour( 'accent', '#2271b1' );
		$link        = $colour( 'link', '#2271b1' );

		return "
#adminmenu,#adminmenuback,#adminmenuwrap{background:{$menu_bg}}
#adminmenu a,#adminmenu div.wp-menu-image:before{color:{$menu_text}}
#adminmenu li.menu-top:hover>a.menu-top,#adminmenu li.opensub>a.menu-top{background:{$active_bg};color:{$active_text}}
#adminmenu .wp-submenu,#adminmenu .wp-has-current-submenu .wp-submenu{background:{$menu_bg}}
#adminmenu li.current a.menu-top,#adminmenu li.wp-has-current-submenu a.wp-has-current-submenu,#adminmenu .wp-submenu a:focus,#adminmenu .wp-submenu li.current a{background:{$active_bg};color:{$active_text}}
#wpadminbar,#wpadminbar .quicklinks>ul>li>a{background:{$bar_bg}}
#wpadminbar .ab-item,#wpadminbar a.ab-item,#wpadminbar>#wp-toolbar span.ab-label{color:{$bar_text}}
#wpbody-content a:not(.button):not(.button-primary):not(.button-secondary){color:{$link}}
.wp-core-ui .button-primary{background:{$accent};border-color:{$accent}}
.wp-core-ui .button-primary:hover,.wp-core-ui .button-primary:focus{background:{$active_bg};border-color:{$active_bg}}
.wp-core-ui .button,.wp-core-ui .button-primary,.wp-core-ui input[type=text],.wp-core-ui select,.postbox,.card{border-radius:{$radius}px}
";
	}

	/**
	 * @param array<string,mixed> $theme Resolved theme.
	 */
	private function login_css( array $theme ): string {
		$bg     = Theme::colour( $theme['login_bg'] ?? null, '#f0f0f1' );
		$card   = Theme::colour( $theme['login_card_bg'] ?? null, '#ffffff' );
		$accent = Theme::colour( $theme['accent'] ?? null, '#2271b1' );
		$link   = Theme::colour( $theme['link'] ?? null, '#2271b1' );
		$radius = max( 0, min( 24, (int) ( $theme['radius'] ?? 4 ) ) );
		$logo   = $this->css_url( (string) ( $theme['login_logo'] ?? '' ) );
		$logo_w = max( 24, min( 480, (int) ( $theme['login_logo_width'] ?? 84 ) ) );

		$css = "
body.login{background:{$bg}}
body.login #login form,body.login .login .message{background:{$card};border-radius:{$radius}px}
body.login #backtoblog a,body.login #nav a,body.login .privacy-policy-page-link a{color:{$link}}
.wp-core-ui .button-primary{background:{$accent};border-color:{$accent};border-radius:{$radius}px}
";

		if ( '' !== $logo ) {
			// `contain` keeps a wide or tall logo whole; core's default is a
			// fixed square that crops anything not shaped like the W mark.
			$css .= "
body.login h1 a{background-image:url('{$logo}');background-size:contain;background-position:center;width:{$logo_w}px;height:{$logo_w}px}
";
		}

		return $css;
	}

	/**
	 * A URL safe to drop inside a CSS `url()` in an inline `<style>`.
	 *
	 * `esc_url()` is for HTML: it encodes ampersands, which corrupts any URL
	 * carrying a query string once it reaches CSS. So the raw escaper restricts
	 * the protocol, and the characters that could terminate the `url()` or the
	 * surrounding style element are removed outright.
	 */
	private function css_url( string $value ): string {
		$url = esc_url_raw( trim( $value ), array( 'http', 'https' ) );

		if ( ! is_string( $url ) || '' === $url ) {
			return '';
		}

		return str_replace(
			array( '(', ')', '"', "'", '\\', '<', '>', "\n", "\r" ),
			'',
			$url
		);
	}

	/**
	 * @return array<string,mixed>|null
	 */
	private function theme(): ?array {
		if ( $this->resolved_once ) {
			return $this->resolved;
		}

		$this->resolved_once = true;
		$this->resolved      = $this->bypassed() ? null : $this->themes->resolve();

		return $this->resolved;
	}

	/**
	 * Gated on `manage_options`, matching the menu editor's bypass: a recovery
	 * route for administrators, not a general way to opt out of branding.
	 */
	private function bypassed(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display toggle for one request; it changes nothing.
		$requested = isset( $_GET[ self::BYPASS_ARG ] ) ? sanitize_key( wp_unslash( (string) $_GET[ self::BYPASS_ARG ] ) ) : '';

		return 'off' === $requested && current_user_can( 'manage_options' );
	}
}
