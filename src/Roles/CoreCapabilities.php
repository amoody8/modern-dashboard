<?php
/**
 * What WordPress's capabilities actually mean.
 *
 * The names are not self-explanatory, and one of them is an outright trap:
 * `edit_posts` does *not* govern editing a published post. That maps to
 * `edit_published_posts`, so an administrator who unticks `edit_posts` and
 * expects editors to stop editing posts is wrong, and nothing in WordPress
 * tells them so.
 *
 * This file exists to make that visible. Capabilities are grouped by what a
 * person is trying to accomplish rather than alphabetically, the post-type
 * families are kept together so revoking one shows its siblings, and every
 * capability carries a plain-language label.
 *
 * @package ModernDashboard
 */

declare( strict_types = 1 );

namespace ModernDashboard\Roles;

defined( 'ABSPATH' ) || exit;

final class CoreCapabilities {

	/**
	 * Capabilities that may never be revoked from a role that can administer.
	 *
	 * Not a UI convenience — enforced in the sanitizer, because the UI can be
	 * bypassed by posting to the REST route directly. Removing `manage_options`
	 * from administrators would also destroy the recovery route, since the
	 * bypass argument on the menu and branding subsystems gates on exactly that
	 * capability.
	 *
	 * @return string[]
	 */
	public static function protected_caps(): array {
		return array(
			'manage_options',
			'manage_network',
			'manage_network_options',
			'manage_network_users',
			'promote_users',
			'edit_users',
			'delete_users',
			'create_users',
			'manage_network_dashboard',
			'view_modern_dashboard',
		);
	}

	/**
	 * Capabilities only a super administrator ever holds. Shown, but never
	 * editable: granting them through a role would be a lie, because core
	 * checks super-admin status before consulting roles at all.
	 *
	 * @return string[]
	 */
	public static function network_only(): array {
		return array(
			'manage_network',
			'manage_sites',
			'manage_network_users',
			'manage_network_themes',
			'manage_network_options',
			'manage_network_plugins',
			'upgrade_network',
			'setup_network',
			'create_sites',
			'delete_sites',

			// These look like ordinary site capabilities but are not, on
			// Multisite. Verified: `activate_plugins` maps to
			// [ activate_plugins, manage_network_plugins ] and core requires
			// *all* mapped capabilities, so granting it to a site role has no
			// effect. Offering the checkbox would be a control that silently
			// does nothing, which is worse than not offering it.
			'activate_plugins',
			'install_plugins',
			'update_plugins',
			'delete_plugins',
			'edit_plugins',
			'install_themes',
			'update_themes',
			'delete_themes',
			'edit_themes',
			'update_core',
			'edit_files',
			'unfiltered_html',
		);
	}

	/**
	 * The grouped catalogue.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function groups(): array {
		return array(
			'posts'      => array(
				'label' => __( 'Posts', 'modern-dashboard' ),
				'note'  => __( 'These work as a family. Editing a post that is already published needs “Edit published posts”, not “Edit posts”.', 'modern-dashboard' ),
				'caps'  => array(
					'edit_posts'             => __( 'Edit posts', 'modern-dashboard' ),
					'edit_others_posts'      => __( 'Edit other people’s posts', 'modern-dashboard' ),
					'edit_published_posts'   => __( 'Edit published posts', 'modern-dashboard' ),
					'edit_private_posts'     => __( 'Edit private posts', 'modern-dashboard' ),
					'publish_posts'          => __( 'Publish posts', 'modern-dashboard' ),
					'delete_posts'           => __( 'Delete posts', 'modern-dashboard' ),
					'delete_others_posts'    => __( 'Delete other people’s posts', 'modern-dashboard' ),
					'delete_published_posts' => __( 'Delete published posts', 'modern-dashboard' ),
					'delete_private_posts'   => __( 'Delete private posts', 'modern-dashboard' ),
					'read_private_posts'     => __( 'Read private posts', 'modern-dashboard' ),
				),
			),
			'pages'      => array(
				'label' => __( 'Pages', 'modern-dashboard' ),
				'caps'  => array(
					'edit_pages'             => __( 'Edit pages', 'modern-dashboard' ),
					'edit_others_pages'      => __( 'Edit other people’s pages', 'modern-dashboard' ),
					'edit_published_pages'   => __( 'Edit published pages', 'modern-dashboard' ),
					'edit_private_pages'     => __( 'Edit private pages', 'modern-dashboard' ),
					'publish_pages'          => __( 'Publish pages', 'modern-dashboard' ),
					'delete_pages'           => __( 'Delete pages', 'modern-dashboard' ),
					'delete_others_pages'    => __( 'Delete other people’s pages', 'modern-dashboard' ),
					'delete_published_pages' => __( 'Delete published pages', 'modern-dashboard' ),
					'delete_private_pages'   => __( 'Delete private pages', 'modern-dashboard' ),
					'read_private_pages'     => __( 'Read private pages', 'modern-dashboard' ),
				),
			),
			'media'      => array(
				'label' => __( 'Media', 'modern-dashboard' ),
				'caps'  => array(
					'upload_files'    => __( 'Upload files', 'modern-dashboard' ),
					'unfiltered_html' => __( 'Post unfiltered HTML', 'modern-dashboard' ),
				),
			),
			'comments'   => array(
				'label' => __( 'Comments', 'modern-dashboard' ),
				'caps'  => array(
					'moderate_comments' => __( 'Moderate comments', 'modern-dashboard' ),
					'edit_comment'      => __( 'Edit a comment', 'modern-dashboard' ),
				),
			),
			'appearance' => array(
				'label' => __( 'Appearance', 'modern-dashboard' ),
				'caps'  => array(
					'switch_themes'      => __( 'Switch themes', 'modern-dashboard' ),
					'edit_theme_options' => __( 'Customise the active theme', 'modern-dashboard' ),
					'edit_themes'        => __( 'Edit theme files', 'modern-dashboard' ),
					'install_themes'     => __( 'Install themes', 'modern-dashboard' ),
					'update_themes'      => __( 'Update themes', 'modern-dashboard' ),
					'delete_themes'      => __( 'Delete themes', 'modern-dashboard' ),
					'customize'          => __( 'Use the customiser', 'modern-dashboard' ),
				),
			),
			'plugins'    => array(
				'label' => __( 'Plugins', 'modern-dashboard' ),
				'caps'  => array(
					'activate_plugins' => __( 'Activate and deactivate plugins', 'modern-dashboard' ),
					'edit_plugins'     => __( 'Edit plugin files', 'modern-dashboard' ),
					'install_plugins'  => __( 'Install plugins', 'modern-dashboard' ),
					'update_plugins'   => __( 'Update plugins', 'modern-dashboard' ),
					'delete_plugins'   => __( 'Delete plugins', 'modern-dashboard' ),
				),
			),
			'users'      => array(
				'label' => __( 'People', 'modern-dashboard' ),
				'note'  => __( 'Several of these cannot be revoked — removing them would take away the way back in.', 'modern-dashboard' ),
				'caps'  => array(
					'list_users'    => __( 'See the user list', 'modern-dashboard' ),
					'create_users'  => __( 'Add users', 'modern-dashboard' ),
					'edit_users'    => __( 'Edit users', 'modern-dashboard' ),
					'delete_users'  => __( 'Delete users', 'modern-dashboard' ),
					'promote_users' => __( 'Change someone’s role', 'modern-dashboard' ),
					'remove_users'  => __( 'Remove users from this site', 'modern-dashboard' ),
				),
			),
			'settings'   => array(
				'label' => __( 'Settings and tools', 'modern-dashboard' ),
				'caps'  => array(
					'manage_options'    => __( 'Change site settings', 'modern-dashboard' ),
					'manage_categories' => __( 'Manage categories and tags', 'modern-dashboard' ),
					'manage_links'      => __( 'Manage links', 'modern-dashboard' ),
					'import'            => __( 'Import content', 'modern-dashboard' ),
					'export'            => __( 'Export content', 'modern-dashboard' ),
					'edit_dashboard'    => __( 'Rearrange the dashboard', 'modern-dashboard' ),
					'update_core'       => __( 'Update WordPress', 'modern-dashboard' ),
					'edit_files'        => __( 'Edit files', 'modern-dashboard' ),
					'read'              => __( 'Access the admin', 'modern-dashboard' ),
				),
			),
			'network'    => array(
				'label' => __( 'Network', 'modern-dashboard' ),
				'note'  => __( 'Held only by network administrators. Shown for reference; granting them through a role has no effect, because WordPress checks network status before it looks at roles.', 'modern-dashboard' ),
				'caps'  => array(
					'manage_network'         => __( 'Manage the network', 'modern-dashboard' ),
					'manage_sites'           => __( 'Manage sites', 'modern-dashboard' ),
					'manage_network_users'   => __( 'Manage network users', 'modern-dashboard' ),
					'manage_network_themes'  => __( 'Manage network themes', 'modern-dashboard' ),
					'manage_network_options' => __( 'Manage network settings', 'modern-dashboard' ),
					'manage_network_plugins' => __( 'Manage network plugins', 'modern-dashboard' ),
					'upgrade_network'        => __( 'Upgrade the network', 'modern-dashboard' ),
				),
			),
		);
	}

	/**
	 * A readable label for a capability, falling back to the raw name.
	 */
	public static function label( string $cap ): string {
		foreach ( self::groups() as $group ) {
			if ( isset( $group['caps'][ $cap ] ) ) {
				return (string) $group['caps'][ $cap ];
			}
		}

		return $cap;
	}

	/**
	 * Which group a capability belongs to; `other` when it came from a plugin
	 * and we have no label for it.
	 */
	public static function group_of( string $cap ): string {
		foreach ( self::groups() as $key => $group ) {
			if ( isset( $group['caps'][ $cap ] ) ) {
				return (string) $key;
			}
		}

		return 'other';
	}

	/**
	 * Every capability this file knows about.
	 *
	 * @return string[]
	 */
	public static function known(): array {
		$caps = array();

		foreach ( self::groups() as $group ) {
			$caps = array_merge( $caps, array_keys( (array) $group['caps'] ) );
		}

		return array_values( array_unique( $caps ) );
	}
}
