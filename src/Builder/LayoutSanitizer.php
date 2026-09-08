<?php
/**
 * Validates a dashboard template against the block registry.
 *
 * Templates arrive from the browser, so nothing in them is trusted: unknown
 * block types are dropped, widths are clamped to what the block declares it can
 * handle, unknown settings keys are discarded, and every value is coerced to the
 * type its schema entry describes. A template that survives this is safe for the
 * renderer to walk without further checking.
 *
 * @package ModernDashboard
 */

declare( strict_types = 1 );

namespace ModernDashboard\Builder;

defined( 'ABSPATH' ) || exit;

final class LayoutSanitizer {

	/** Upper bound on blocks per template; well past any sane dashboard. */
	public const MAX_BLOCKS = 60;

	private BlockRegistry $registry;

	public function __construct( BlockRegistry $registry ) {
		$this->registry = $registry;
	}

	/**
	 * @param array<string,mixed> $input Untrusted template.
	 *
	 * @return array<string,mixed> A template safe to store and render.
	 */
	public function template( array $input ): array {
		$id = sanitize_key( (string) ( $input['id'] ?? '' ) );

		if ( '' === $id ) {
			$id = 'tpl_' . substr( md5( (string) wp_rand() . microtime() ), 0, 12 );
		}

		$name = sanitize_text_field( (string) ( $input['name'] ?? '' ) );

		return array(
			'id'      => $id,
			'name'    => '' === $name ? __( 'Untitled dashboard', 'modern-dashboard' ) : $name,
			'blocks'  => $this->blocks( (array) ( $input['blocks'] ?? array() ) ),
			'updated' => time(),
		);
	}

	/**
	 * @param array<int,mixed> $input Untrusted block list.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function blocks( array $input ): array {
		$blocks = array();
		$seen   = array();

		foreach ( $input as $raw ) {
			if ( count( $blocks ) >= self::MAX_BLOCKS ) {
				break;
			}

			if ( ! is_array( $raw ) ) {
				continue;
			}

			$type       = sanitize_key( (string) ( $raw['type'] ?? '' ) );
			$definition = $this->registry->get( $type );

			if ( null === $definition ) {
				continue;
			}

			$id = sanitize_key( (string) ( $raw['id'] ?? '' ) );

			// Duplicate or missing ids would break React keys and the builder's
			// selection model, so mint a fresh one rather than trusting input.
			if ( '' === $id || isset( $seen[ $id ] ) ) {
				$id = 'blk_' . substr( md5( $type . wp_rand() . microtime() ), 0, 12 );
			}

			$seen[ $id ] = true;

			$blocks[] = array(
				'id'       => $id,
				'type'     => $type,
				'width'    => $this->width( $raw['width'] ?? null, $definition ),
				'settings' => $this->settings( (array) ( $raw['settings'] ?? array() ), $definition ),
			);
		}

		return $blocks;
	}

	/**
	 * @param mixed               $value      Requested width in twelfths.
	 * @param array<string,mixed> $definition Block definition.
	 */
	private function width( mixed $value, array $definition ): int {
		$min = max( 1, (int) ( $definition['min_width'] ?? 1 ) );
		$max = min( BlockRegistry::COLUMNS, (int) ( $definition['max_width'] ?? BlockRegistry::COLUMNS ) );

		if ( $max < $min ) {
			$max = $min;
		}

		// A block arriving without a width takes the type's preferred width
		// rather than sprawling to its maximum.
		$preferred = (int) ( $definition['default_width'] ?? $max );
		$width     = null === $value ? $preferred : (int) $value;

		return max( $min, min( $max, $width ) );
	}

	/**
	 * Coerce each setting to the type its schema entry declares, filling in the
	 * block's default whenever the incoming value is missing or unusable.
	 *
	 * @param array<string,mixed> $input      Untrusted settings.
	 * @param array<string,mixed> $definition Block definition.
	 *
	 * @return array<string,mixed>
	 */
	private function settings( array $input, array $definition ): array {
		$schema   = (array) ( $definition['settings'] ?? array() );
		$defaults = (array) ( $definition['defaults'] ?? array() );
		$clean    = array();

		foreach ( $schema as $key => $spec ) {
			$default = $defaults[ $key ] ?? '';

			if ( ! array_key_exists( $key, $input ) ) {
				$clean[ $key ] = $default;

				continue;
			}

			$clean[ $key ] = $this->setting_value( $input[ $key ], (array) $spec, $default );
		}

		return $clean;
	}

	/**
	 * @param mixed               $value   Untrusted value.
	 * @param array<string,mixed> $spec    Schema entry for this setting.
	 * @param mixed               $fallback Fallback when the value is unusable.
	 */
	private function setting_value( mixed $value, array $spec, mixed $fallback ): mixed {
		switch ( (string) ( $spec['type'] ?? 'text' ) ) {
			case 'number':
				$number = (int) $value;
				$min    = isset( $spec['min'] ) ? (int) $spec['min'] : PHP_INT_MIN;
				$max    = isset( $spec['max'] ) ? (int) $spec['max'] : PHP_INT_MAX;

				return max( $min, min( $max, $number ) );

			case 'select':
				// Matched as strings because JSON turns an int option (the
				// heading level) into a string on the way in, then the schema's
				// own value is returned so the stored type stays canonical.
				foreach ( (array) ( $spec['options'] ?? array() ) as $option ) {
					if ( isset( $option['value'] ) && (string) $option['value'] === (string) $value ) {
						return $option['value'];
					}
				}

				return $fallback;

			case 'toggle':
				return (bool) $value;

			case 'textarea':
				return sanitize_textarea_field( (string) $value );

			default:
				return sanitize_text_field( (string) $value );
		}
	}
}
