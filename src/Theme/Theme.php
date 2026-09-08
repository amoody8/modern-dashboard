<?php
/**
 * The theme shape, its defaults, and its sanitizer.
 *
 * Every colour here is interpolated into a stylesheet, so colours are validated
 * as hex and anything that fails validation falls back to the default rather
 * than being passed through. There is no path by which arbitrary text reaches
 * the CSS.
 *
 * @package ModernDashboard
 */

declare( strict_types = 1 );

namespace ModernDashboard\Theme;

defined( 'ABSPATH' ) || exit;

final class Theme {

	/** Colour fields, mapped to their defaults (WordPress's own admin palette). */
	private const COLOURS = array(
		'menu_bg'          => '#1d2327',
		'menu_text'        => '#f0f0f1',
		'menu_active_bg'   => '#2271b1',
		'menu_active_text' => '#ffffff',
		'bar_bg'           => '#1d2327',
		'bar_text'         => '#f0f0f1',
		'accent'           => '#2271b1',
		'link'             => '#2271b1',
		'login_bg'         => '#f0f0f1',
		'login_card_bg'    => '#ffffff',
	);

	/**
	 * @return array<string,mixed>
	 */
	public static function defaults(): array {
		return array_merge(
			self::COLOURS,
			array(
				'enabled'          => false,
				'radius'           => 4,
				'login_logo'       => '',
				'login_logo_width' => 84,
				'login_url'        => '',
				'login_title'      => '',
				'footer_text'      => '',
				'howdy_text'       => '',
				'hide_wp_logo'     => false,
			)
		);
	}

	/**
	 * @return array<int,string>
	 */
	public static function colour_keys(): array {
		return array_keys( self::COLOURS );
	}

	/**
	 * Foreground/background pairs the editor checks for legibility. Kept here so
	 * the contrast warnings in the UI describe the pairs that are actually
	 * rendered together, rather than a guess.
	 *
	 * @return array<int,array{fg:string,bg:string,label:string}>
	 */
	public static function contrast_pairs(): array {
		return array(
			array(
				'fg'    => 'menu_text',
				'bg'    => 'menu_bg',
				'label' => __( 'Menu text on menu background', 'modern-dashboard' ),
			),
			array(
				'fg'    => 'menu_active_text',
				'bg'    => 'menu_active_bg',
				'label' => __( 'Current menu item', 'modern-dashboard' ),
			),
			array(
				'fg'    => 'bar_text',
				'bg'    => 'bar_bg',
				'label' => __( 'Admin bar text', 'modern-dashboard' ),
			),
		);
	}

	/**
	 * @param array<string,mixed> $input Untrusted theme values.
	 *
	 * @return array<string,mixed>
	 */
	public static function sanitize( array $input ): array {
		$defaults = self::defaults();
		$clean    = array();

		foreach ( self::COLOURS as $key => $default ) {
			$clean[ $key ] = self::colour( $input[ $key ] ?? null, $default );
		}

		$clean['enabled']          = (bool) ( $input['enabled'] ?? $defaults['enabled'] );
		$clean['radius']           = max( 0, min( 24, (int) ( $input['radius'] ?? $defaults['radius'] ) ) );
		$clean['login_logo']       = self::url( $input['login_logo'] ?? '' );
		$clean['login_logo_width'] = max( 24, min( 480, (int) ( $input['login_logo_width'] ?? $defaults['login_logo_width'] ) ) );
		$clean['login_url']        = self::url( $input['login_url'] ?? '' );
		$clean['login_title']      = self::text( $input['login_title'] ?? '', 120 );
		$clean['footer_text']      = self::text( $input['footer_text'] ?? '', 200 );
		$clean['howdy_text']       = self::text( $input['howdy_text'] ?? '', 40 );
		$clean['hide_wp_logo']     = (bool) ( $input['hide_wp_logo'] ?? $defaults['hide_wp_logo'] );

		return $clean;
	}

	/**
	 * Hex only. `sanitize_hex_color()` returns null for anything else, which is
	 * what keeps `url(javascript:…)` and friends out of the stylesheet.
	 */
	public static function colour( mixed $value, string $fallback ): string {
		$hex = sanitize_hex_color( is_string( $value ) ? trim( $value ) : '' );

		return is_string( $hex ) && '' !== $hex ? $hex : $fallback;
	}

	/**
	 * Only http(s) URLs survive, so a logo cannot become a `javascript:` or
	 * `data:` payload.
	 */
	private static function url( mixed $value ): string {
		$url = esc_url_raw( is_string( $value ) ? trim( $value ) : '', array( 'http', 'https' ) );

		return is_string( $url ) ? substr( $url, 0, 500 ) : '';
	}

	private static function text( mixed $value, int $length ): string {
		return substr( trim( sanitize_text_field( (string) $value ) ), 0, $length );
	}

	/**
	 * JSON Schema for the REST endpoints.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function rest_schema(): array {
		$schema = array();

		foreach ( self::colour_keys() as $key ) {
			$schema[ $key ] = array( 'type' => 'string' );
		}

		return array_merge(
			$schema,
			array(
				'enabled'          => array( 'type' => 'boolean' ),
				'radius'           => array(
					'type'    => 'integer',
					'minimum' => 0,
					'maximum' => 24,
				),
				'login_logo'       => array( 'type' => 'string' ),
				'login_logo_width' => array(
					'type'    => 'integer',
					'minimum' => 24,
					'maximum' => 480,
				),
				'login_url'        => array( 'type' => 'string' ),
				'login_title'      => array( 'type' => 'string' ),
				'footer_text'      => array( 'type' => 'string' ),
				'howdy_text'       => array( 'type' => 'string' ),
				'hide_wp_logo'     => array( 'type' => 'boolean' ),
			)
		);
	}
}
