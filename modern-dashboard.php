<?php
/**
 * Plugin Name:       Modern Dashboard
 * Plugin URI:        https://github.com/amoody8/modern-dashboard
 * Description:       A network-first admin dashboard for WordPress Multisite. Aggregates content, users, updates and storage across every site in the network into one control surface.
 * Version:           0.3.0
 * Requires at least: 6.6
 * Requires PHP:      8.0
 * Author:            Allan Moody
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       modern-dashboard
 * Domain Path:       /languages
 * Network:           true
 *
 * @package ModernDashboard
 */

declare( strict_types = 1 );

namespace ModernDashboard;

defined( 'ABSPATH' ) || exit;

const VERSION     = '0.3.0';
const PLUGIN_FILE = __FILE__;

require_once __DIR__ . '/src/Autoloader.php';

Autoloader::register( __NAMESPACE__, __DIR__ . '/src' );

/**
 * Shared plugin instance.
 */
function plugin(): Plugin {
	static $plugin = null;

	if ( null === $plugin ) {
		$plugin = new Plugin( PLUGIN_FILE, VERSION );
	}

	return $plugin;
}

plugin()->boot();

register_activation_hook( __FILE__, array( Plugin::class, 'on_activate' ) );
register_deactivation_hook( __FILE__, array( Plugin::class, 'on_deactivate' ) );
