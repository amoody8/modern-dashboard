<?php
/**
 * Storage and role assignment for dashboard templates.
 *
 * Templates live in one network option, which is what makes them
 * network-controlled: the network admin authors them centrally and every site
 * inherits. Assignment is by role, with a default that catches anyone whose role
 * has no template of its own.
 *
 * @package ModernDashboard
 */

declare( strict_types = 1 );

namespace ModernDashboard\Builder;

defined( 'ABSPATH' ) || exit;

final class TemplateRepository {

	public const OPTION = 'modern_dashboard_templates';

	/** Pseudo-role for network administrators, who have no per-site role. */
	public const SUPER_ADMIN_ROLE = 'super-admin';

	private BlockRegistry $registry;
	private LayoutSanitizer $sanitizer;

	/** @var array<string,mixed>|null */
	private ?array $cache = null;

	public function __construct( BlockRegistry $registry, LayoutSanitizer $sanitizer ) {
		$this->registry  = $registry;
		$this->sanitizer = $sanitizer;
	}

	/**
	 * The whole stored structure, seeded on first use.
	 *
	 * @return array{templates:array<string,array<string,mixed>>,assignments:array<string,string>,default:string}
	 */
	public function state(): array {
		if ( null !== $this->cache ) {
			return $this->cache;
		}

		$stored = get_network_option( null, self::OPTION, null );

		if ( ! is_array( $stored ) || empty( $stored['templates'] ) ) {
			$stored = $this->seed();
			update_network_option( null, self::OPTION, $stored );
		}

		$this->cache = array(
			'templates'   => (array) ( $stored['templates'] ?? array() ),
			'assignments' => array_map( 'strval', (array) ( $stored['assignments'] ?? array() ) ),
			'default'     => (string) ( $stored['default'] ?? 'default' ),
		);

		return $this->cache;
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public function all(): array {
		return array_values( $this->state()['templates'] );
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public function get( string $id ): ?array {
		return $this->state()['templates'][ $id ] ?? null;
	}

	/**
	 * Create or replace a template.
	 *
	 * @param array<string,mixed> $input Untrusted template.
	 *
	 * @return array<string,mixed> The stored, sanitized template.
	 */
	public function save( array $input ): array {
		$template = $this->sanitizer->template( $input );
		$state    = $this->state();

		$state['templates'][ $template['id'] ] = $template;

		$this->persist( $state );

		return $template;
	}

	/**
	 * Delete a template.
	 *
	 * Refuses to remove the last one or the default, since either would leave
	 * users with no dashboard at all. Assignments pointing at the deleted
	 * template fall back to the default rather than dangling.
	 *
	 * @return true|\WP_Error
	 */
	public function delete( string $id ): bool|\WP_Error {
		$state = $this->state();

		if ( ! isset( $state['templates'][ $id ] ) ) {
			return new \WP_Error(
				'modern_dashboard_no_template',
				__( 'That dashboard template does not exist.', 'modern-dashboard' ),
				array( 'status' => 404 )
			);
		}

		if ( $id === $state['default'] ) {
			return new \WP_Error(
				'modern_dashboard_default_template',
				__( 'The default dashboard cannot be deleted. Make another one the default first.', 'modern-dashboard' ),
				array( 'status' => 400 )
			);
		}

		if ( count( $state['templates'] ) <= 1 ) {
			return new \WP_Error(
				'modern_dashboard_last_template',
				__( 'At least one dashboard template has to exist.', 'modern-dashboard' ),
				array( 'status' => 400 )
			);
		}

		unset( $state['templates'][ $id ] );

		$state['assignments'] = array_filter(
			$state['assignments'],
			static fn( string $assigned ): bool => $assigned !== $id
		);

		$this->persist( $state );

		return true;
	}

	/**
	 * Replace the role assignment map and the default template.
	 *
	 * @param array<string,mixed> $assignments Role => template ID.
	 * @param string|null         $fallback    Template ID to make the default.
	 *
	 * @return array{assignments:array<string,string>,default:string}
	 */
	public function assign( array $assignments, ?string $fallback = null ): array {
		$state = $this->state();
		$clean = array();

		foreach ( $assignments as $role => $template_id ) {
			$role        = sanitize_key( (string) $role );
			$template_id = sanitize_key( (string) $template_id );

			// Silently dropping an assignment to a template that does not exist
			// keeps the map from accumulating dead references.
			if ( '' !== $role && isset( $state['templates'][ $template_id ] ) ) {
				$clean[ $role ] = $template_id;
			}
		}

		$state['assignments'] = $clean;

		if ( null !== $fallback ) {
			$fallback = sanitize_key( $fallback );

			if ( isset( $state['templates'][ $fallback ] ) ) {
				$state['default'] = $fallback;
			}
		}

		$this->persist( $state );

		return array(
			'assignments' => $state['assignments'],
			'default'     => $state['default'],
		);
	}

	/**
	 * The template a given user should see.
	 *
	 * Network administrators match the `super-admin` pseudo-role first; everyone
	 * else matches on their roles for the current site. Falls back to the
	 * default, and to the seeded layout if even that has gone missing.
	 *
	 * @return array<string,mixed>
	 */
	public function resolve_for_user( ?\WP_User $user = null ): array {
		$user  = $user ?? wp_get_current_user();
		$state = $this->state();
		$roles = array();

		if ( $user instanceof \WP_User && $user->exists() ) {
			if ( is_multisite() && is_super_admin( $user->ID ) ) {
				$roles[] = self::SUPER_ADMIN_ROLE;
			}

			$roles = array_merge( $roles, array_map( 'strval', (array) $user->roles ) );
		}

		foreach ( $roles as $role ) {
			if ( isset( $state['assignments'][ $role ], $state['templates'][ $state['assignments'][ $role ] ] ) ) {
				return $state['templates'][ $state['assignments'][ $role ] ];
			}
		}

		return $state['templates'][ $state['default'] ] ?? $this->default_template();
	}

	/**
	 * Roles a template can be assigned to, for the assignment UI.
	 *
	 * @return array<int,array{value:string,label:string}>
	 */
	public function assignable_roles(): array {
		$roles = array(
			array(
				'value' => self::SUPER_ADMIN_ROLE,
				'label' => __( 'Network administrators', 'modern-dashboard' ),
			),
		);

		$names = function_exists( 'wp_roles' ) ? wp_roles()->get_names() : array();

		foreach ( $names as $slug => $label ) {
			$roles[] = array(
				'value' => (string) $slug,
				'label' => translate_user_role( (string) $label ),
			);
		}

		return $roles;
	}

	/**
	 * @param array{templates:array<string,array<string,mixed>>,assignments:array<string,string>,default:string} $state State to write.
	 */
	private function persist( array $state ): void {
		$this->cache = $state;

		update_network_option( null, self::OPTION, $state );
	}

	/**
	 * @return array{templates:array<string,array<string,mixed>>,assignments:array<string,string>,default:string}
	 */
	private function seed(): array {
		$default = $this->default_template();

		return array(
			'templates'   => array( $default['id'] => $default ),
			'assignments' => array(),
			'default'     => $default['id'],
		);
	}

	/**
	 * The layout new networks start on — a faithful copy of the built-in
	 * overview, so the builder begins from something already useful rather than
	 * an empty canvas.
	 *
	 * @return array<string,mixed>
	 */
	public function default_template(): array {
		$block = static fn( string $type, int $width, array $settings = array() ): array => array(
			'type'     => $type,
			'width'    => $width,
			'settings' => $settings,
		);

		return $this->sanitizer->template(
			array(
				'id'     => 'default',
				'name'   => __( 'Network overview', 'modern-dashboard' ),
				'blocks' => array(
					$block( 'stat', 3, array( 'metric' => 'sites' ) ),
					$block( 'stat', 3, array( 'metric' => 'users_unique' ) ),
					$block( 'stat', 3, array( 'metric' => 'content' ) ),
					$block( 'stat', 3, array( 'metric' => 'updates' ) ),
					$block( 'stat', 3, array( 'metric' => 'storage' ) ),
					$block( 'stat', 3, array( 'metric' => 'attention' ) ),
					$block( 'freshness', 6 ),
					$block(
						'attention',
						7,
						array(
							'limit' => 10,
						)
					),
					$block( 'environment', 5 ),
					$block(
						'chart',
						12,
						array(
							'source' => 'top_sites',
							'metric' => 'users',
							'limit'  => 8,
						)
					),
				),
			)
		);
	}
}
