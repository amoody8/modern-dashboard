<?php
/**
 * Smoke tests for the parts of the plugin that are pure logic.
 *
 * Run with  (or ). No WordPress install
 * needed: the handful of WordPress functions these paths touch are stubbed
 * below. This deliberately does not cover the collectors, which only mean
 * anything against a real network.
 *
 * @package ModernDashboard
 */
declare( strict_types = 1 );

// --- minimal WordPress stub ------------------------------------------------

define( 'ABSPATH', __DIR__ );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['net_options'] = [];
$GLOBALS['meta']        = [];

function get_network_option( $n, $key, $default = false ) {
	return $GLOBALS['net_options'][ $key ] ?? $default;
}
function update_network_option( $n, $key, $value ) {
	$GLOBALS['net_options'][ $key ] = $value;
	return true;
}
function delete_network_option( $n, $key ) {
	unset( $GLOBALS['net_options'][ $key ] );
	return true;
}
function is_site_meta_supported() { return true; }
function get_site_meta( $id, $key, $single = false ) { return $GLOBALS['meta'][ $id ][ $key ] ?? ( $single ? '' : [] ); }
function update_site_meta( $id, $key, $value ) { $GLOBALS['meta'][ $id ][ $key ] = $value; return true; }
function delete_site_meta( $id, $key ) { unset( $GLOBALS['meta'][ $id ][ $key ] ); return true; }
function update_meta_cache( $type, $ids ) { return true; }
function untrailingslashit( $s ) { return rtrim( (string) $s, '/' ); }
function apply_filters( $tag, $value, ...$rest ) { return $value; }
function do_action( $tag, ...$args ) {}
function wp_parse_args( $args, $defaults ) { return array_merge( $defaults, $args ); }
function __( $t, $d = null ) { return $t; }
function absint( $v ) { return abs( (int) $v ); }
function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ); }
function sanitize_text_field( $t ) { return trim( strip_tags( (string) $t ) ); }
function sanitize_textarea_field( $t ) { return trim( strip_tags( (string) $t ) ); }
function wp_rand( $min = 0, $max = PHP_INT_MAX ) { return random_int( $min, min( $max, PHP_INT_MAX ) ); }
function translate_user_role( $r ) { return $r; }
function is_multisite() { return true; }
function is_super_admin( $id = 0 ) { return in_array( (int) $id, $GLOBALS['super_admins'] ?? array(), true ); }
function wp_get_current_user() { return $GLOBALS['current_user'] ?? null; }
function is_wp_error( $t ) { return $t instanceof WP_Error; }
function wp_roles() { return new class() {
	public function get_names() { return array( 'administrator' => 'Administrator', 'editor' => 'Editor' ); }
}; }

class WP_Error {
	public $code;
	public function __construct( $code = '', $message = '', $data = array() ) { $this->code = $code; }
	public function get_error_code() { return $this->code; }
}

class WP_User {
	public $ID;
	public $roles;
	public function __construct( int $id, array $roles = array() ) { $this->ID = $id; $this->roles = $roles; }
	public function exists() { return $this->ID > 0; }
}

class WP_Site {
	public $blog_id, $blogname, $domain, $path, $siteurl;
	public $public = 1, $archived = 0, $spam = 0, $deleted = 0, $mature = 0;
	public $registered = '2024-01-01 00:00:00', $last_updated = '2024-01-01 00:00:00';
	public function __construct( array $p ) { foreach ( $p as $k => $v ) { $this->$k = $v; } }
}

$GLOBALS['sites'] = [];
function get_sites( $args = [] ) { return array_keys( $GLOBALS['sites'] ); }
function get_site( $id ) { return $GLOBALS['sites'][ $id ] ?? null; }

require_once dirname( __DIR__ ) . '/src/Autoloader.php';
ModernDashboard\Autoloader::register( 'ModernDashboard', dirname( __DIR__ ) . '/src' );

use ModernDashboard\Data\MetricsRepository;
use ModernDashboard\Data\SiteCollector;
use ModernDashboard\Data\Store;
use ModernDashboard\Settings\Settings;

$pass = 0;
$fail = 0;
function check( string $what, $actual, $expected ): void {
	global $pass, $fail;
	if ( $actual === $expected ) {
		++$pass;
		echo "  ok   $what\n";
	} else {
		++$fail;
		echo "  FAIL $what\n       expected: " . var_export( $expected, true ) . "\n       actual:   " . var_export( $actual, true ) . "\n";
	}
}

// --- Settings::sanitize ----------------------------------------------------

echo "Settings::sanitize\n";
$settings = new Settings();

$clean = $settings->sanitize( [
	'refresh_interval'        => 'every_second',      // not in the allow list
	'batch_size'              => 99999,               // over the cap
	'storage_scan_timeout'    => 0,                   // under the floor
	'stale_after'             => 1,                   // under the floor
	'inactive_threshold_days' => '45',                // numeric string
	'excluded_sites'          => [ '12', 'abc', 0, 34, 34 ],
	'collect_storage'         => 'yes',
	'allow_site_admins'       => 0,
] );

check( 'unknown interval falls back', $clean['refresh_interval'], 'hourly' );
check( 'batch_size capped at 200', $clean['batch_size'], 200 );
check( 'scan timeout floored at 1', $clean['storage_scan_timeout'], 1 );
check( 'stale_after floored at 300', $clean['stale_after'], 300 );
check( 'numeric string coerced', $clean['inactive_threshold_days'], 45 );
check( 'excluded sites cleaned + deduped', $clean['excluded_sites'], [ 12, 34 ] );
check( 'truthy string is true', $clean['collect_storage'], true );
check( 'zero is false', $clean['allow_site_admins'], false );

// --- Store index and staleness --------------------------------------------

echo "\nStore staleness index\n";
$store = new Store();
$now   = time();

$store->set( 1, [ 'collected_at' => $now - 100 ] );
$store->set( 2, [ 'collected_at' => $now - 5000 ] );
$store->set( 3, [ 'collected_at' => $now - 20 ] );

check( 'stalest ordering', $store->stalest( [ 1, 2, 3 ], 3 ), [ 2, 1, 3 ] );
check( 'never-collected sorts first', $store->stalest( [ 1, 2, 3, 9 ], 2 ), [ 9, 2 ] );
check( 'limit respected', count( $store->stalest( [ 1, 2, 3 ], 1 ) ), 1 );
check( 'zero limit returns nothing', $store->stalest( [ 1, 2, 3 ], 0 ), [] );

$store->prune( [ 1, 3 ] );
check( 'prune drops departed sites', array_keys( $store->index() ), [ 1, 3 ] );

// --- MetricsRepository query ----------------------------------------------

echo "\nMetricsRepository::query\n";

$GLOBALS['net_options'] = [];
$GLOBALS['meta']        = [];
$store                  = new Store();

$make = function ( int $id, string $name, int $users, int $posts, int $updates, bool $spam = false, ?int $last = null ) use ( $now ) {
	return [
		'blog_id'      => $id,
		'name'         => $name,
		'url'          => "https://$name.example.com",
		'domain'       => "$name.example.com",
		'path'         => '/',
		'registered'   => $now - 86400,
		'last_updated' => $now,
		'flags'        => [ 'public' => true, 'archived' => false, 'spam' => $spam, 'deleted' => false, 'mature' => false ],
		'content'      => [ 'posts' => $posts, 'pages' => 0, 'media' => 0, 'comments' => 0, 'comments_pending' => 0, 'last_published' => $last ],
		'users'        => [ 'total' => $users, 'roles' => [], 'administrators' => 1 ],
		'updates'      => [ 'plugin_updates' => $updates, 'theme_updates' => 0, 'active_plugins' => 3 ],
		'storage'      => [ 'bytes' => $id * 1000, 'partial' => false, 'skipped' => false ],
		'errors'       => [],
		'collected_at' => $now - 10,
	];
};

$fixtures = [
	1 => $make( 1, 'alpha', 10, 5, 0, false, $now - 3600 ),
	2 => $make( 2, 'charlie', 30, 1, 2, false, $now - 3600 ),
	3 => $make( 3, 'bravo', 20, 9, 0, true, $now - ( 400 * 86400 ) ),
];

foreach ( $fixtures as $id => $data ) {
	$GLOBALS['sites'][ $id ] = new WP_Site( [ 'blog_id' => $id, 'blogname' => $data['name'], 'domain' => $data['domain'], 'path' => '/', 'siteurl' => $data['url'] ] );
	$store->set( $id, $data );
}

$repo = new MetricsRepository( $store, new SiteCollector( new Settings() ) );

$names = static fn( array $r ): array => array_column( $r['items'], 'name' );

check( 'default sort is name asc', $names( $repo->query() ), [ 'alpha', 'bravo', 'charlie' ] );
check( 'sort by users desc', $names( $repo->query( [ 'orderby' => 'users', 'order' => 'desc' ] ) ), [ 'charlie', 'bravo', 'alpha' ] );
check( 'sort by content asc', $names( $repo->query( [ 'orderby' => 'content' ] ) ), [ 'charlie', 'alpha', 'bravo' ] );
check( 'search matches name', $names( $repo->query( [ 'search' => 'brav' ] ) ), [ 'bravo' ] );
check( 'search matches domain', $names( $repo->query( [ 'search' => 'charlie.example' ] ) ), [ 'charlie' ] );
check( 'search is case insensitive', $names( $repo->query( [ 'search' => 'ALPHA' ] ) ), [ 'alpha' ] );
check( 'filter spam status', $names( $repo->query( [ 'status' => 'spam' ] ) ), [ 'bravo' ] );
check( 'filter needs_updates', $names( $repo->query( [ 'flag' => 'needs_updates' ] ) ), [ 'charlie' ] );
check( 'filter inactive', $names( $repo->query( [ 'flag' => 'inactive' ] ) ), [ 'bravo' ] );
check( 'filter attention', $names( $repo->query( [ 'flag' => 'attention' ] ) ), [ 'bravo', 'charlie' ] );

$paged = $repo->query( [ 'per_page' => 2, 'page' => 2 ] );
check( 'pagination page 2', $names( $paged ), [ 'charlie' ] );
check( 'pagination reports total', $paged['total'], 3 );
check( 'pagination reports pages', $paged['pages'], 2 );

$over = $repo->query( [ 'per_page' => 2, 'page' => 99 ] );
check( 'page beyond the end clamps', $over['page'], 2 );

// A site in the network with no stored metrics still has to appear.
$GLOBALS['sites'][4] = new WP_Site( [ 'blog_id' => 4, 'blogname' => 'delta', 'domain' => 'delta.example.com', 'path' => '/', 'siteurl' => 'https://delta.example.com' ] );
$uncollected = $repo->query( [ 'search' => 'delta' ] );
check( 'uncollected site still listed', $names( $uncollected ), [ 'delta' ] );
check( 'uncollected site flagged', $uncollected['items'][0]['never_collected'], true );

// --- LayoutSanitizer -------------------------------------------------------

echo "\nLayoutSanitizer\n";

$registry  = new ModernDashboard\Builder\BlockRegistry();
$sanitizer = new ModernDashboard\Builder\LayoutSanitizer( $registry );

$layout = $sanitizer->template(
	array(
		'id'     => 'My Template!!',
		'name'   => '  Ops board  ',
		'blocks' => array(
			array( 'type' => 'not_a_real_block' ),
			array( 'type' => 'stat', 'width' => 99, 'settings' => array( 'metric' => 'users_unique' ) ),
			array( 'type' => 'stat', 'width' => 0 ),
			array( 'type' => 'stat' ),
			array( 'type' => 'chart', 'settings' => array( 'limit' => 9999, 'source' => 'made_up' ) ),
			array( 'type' => 'text', 'settings' => array( 'text' => '<script>x</script>hello', 'bogus' => 'drop me' ) ),
			'not even an array',
		),
	)
);

check( 'template id slugified', $layout['id'], 'mytemplate' );
check( 'template name trimmed', $layout['name'], 'Ops board' );
check( 'unknown block type dropped', count( $layout['blocks'] ), 5 );
check( 'width clamped to max', $layout['blocks'][0]['width'], 12 );
check( 'width clamped to min', $layout['blocks'][1]['width'], 2 );
check( 'missing width uses default_width', $layout['blocks'][2]['width'], 3 );
check( 'known setting kept', $layout['blocks'][0]['settings']['metric'], 'users_unique' );
check( 'number setting clamped', $layout['blocks'][3]['settings']['limit'], 20 );
check( 'invalid select falls back to default', $layout['blocks'][3]['settings']['source'], 'top_sites' );
check( 'markup stripped from textarea', $layout['blocks'][4]['settings']['text'], 'xhello' );
check( 'unknown setting key dropped', array_key_exists( 'bogus', $layout['blocks'][4]['settings'] ), false );

$ids = array_column( $layout['blocks'], 'id' );
check( 'every block got an id', count( array_filter( $ids ) ), 5 );
check( 'block ids are unique', count( array_unique( $ids ) ), 5 );

// A select value arriving as a JSON string must be stored with the schema's type.
$typed = $sanitizer->blocks(
	array( array( 'type' => 'heading', 'settings' => array( 'text' => 'Ops', 'level' => '3' ) ) )
);
check( 'select value stored with canonical type', $typed[0]['settings']['level'], 3 );

$dupes = $sanitizer->blocks(
	array(
		array( 'id' => 'same', 'type' => 'stat' ),
		array( 'id' => 'same', 'type' => 'stat' ),
	)
);
check( 'duplicate ids re-minted', count( array_unique( array_column( $dupes, 'id' ) ) ), 2 );

$flood = $sanitizer->blocks( array_fill( 0, 200, array( 'type' => 'stat' ) ) );
check( 'block count capped', count( $flood ), ModernDashboard\Builder\LayoutSanitizer::MAX_BLOCKS );

// --- TemplateRepository ----------------------------------------------------

echo "\nTemplateRepository\n";

$GLOBALS['net_options'] = array();
$GLOBALS['super_admins'] = array( 1 );

$templates = new ModernDashboard\Builder\TemplateRepository( $registry, $sanitizer );

check( 'seeds one template', count( $templates->all() ), 1 );
check( 'seeded template is the default', $templates->state()['default'], 'default' );

$saved = $templates->save( array( 'id' => 'ops', 'name' => 'Ops', 'blocks' => array( array( 'type' => 'stat' ) ) ) );
check( 'save round-trips', $templates->get( 'ops' )['name'], 'Ops' );
check( 'saved template is listed', count( $templates->all() ), 2 );

$refused = $templates->delete( 'default' );
check( 'refuses to delete the default', is_wp_error( $refused ) ? $refused->get_error_code() : 'no error', 'modern_dashboard_default_template' );

$missing = $templates->delete( 'nope' );
check( 'delete of unknown template errors', is_wp_error( $missing ) ? $missing->get_error_code() : 'no error', 'modern_dashboard_no_template' );

$assigned = $templates->assign( array( 'editor' => 'ops', 'ghost' => 'does_not_exist' ) );
check( 'valid assignment kept', $assigned['assignments']['editor'], 'ops' );
check( 'assignment to missing template dropped', array_key_exists( 'ghost', $assigned['assignments'] ), false );

$GLOBALS['current_user'] = new WP_User( 5, array( 'editor' ) );
check( 'editor resolves to assigned template', $templates->resolve_for_user()['id'], 'ops' );

$GLOBALS['current_user'] = new WP_User( 7, array( 'author' ) );
check( 'unassigned role falls back to default', $templates->resolve_for_user()['id'], 'default' );

$templates->assign( array( ModernDashboard\Builder\TemplateRepository::SUPER_ADMIN_ROLE => 'ops' ) );
$GLOBALS['current_user'] = new WP_User( 1, array( 'administrator' ) );
check( 'super admin matches its pseudo-role first', $templates->resolve_for_user()['id'], 'ops' );

$templates->assign( array(), 'ops' );
check( 'default can be changed', $templates->state()['default'], 'ops' );
check( 'deleting a non-default template works', $templates->delete( 'default' ), true );
check( 'template list shrinks', count( $templates->all() ), 1 );

$last = $templates->delete( 'ops' );
check( 'refuses to delete the only remaining template', is_wp_error( $last ) ? $last->get_error_code() : 'no error', 'modern_dashboard_default_template' );

echo "\n$pass passed, $fail failed\n";
exit( $fail === 0 ? 0 : 1 );
