<?php
/**
 * Plugin container and boot sequence.
 *
 * @package ModernDashboard
 */

declare( strict_types = 1 );

namespace ModernDashboard;

use ModernDashboard\Admin\Assets;
use ModernDashboard\Builder\BlockRegistry;
use ModernDashboard\Builder\LayoutSanitizer;
use ModernDashboard\Builder\TemplateRepository;
use ModernDashboard\Admin\NetworkAdminPage;
use ModernDashboard\Cron\Scheduler;
use ModernDashboard\Data\MetricsRepository;
use ModernDashboard\Data\NetworkAggregator;
use ModernDashboard\Data\SiteCollector;
use ModernDashboard\Data\Store;
use ModernDashboard\Menu\MenuApplier;
use ModernDashboard\Menu\MenuCatalogue;
use ModernDashboard\Menu\MenuRules;
use ModernDashboard\Admin\PaletteGate;
use ModernDashboard\Palette\CommandRegistry;
use ModernDashboard\Palette\CommandResolver;
use ModernDashboard\Palette\IndexBuilder;
use ModernDashboard\Palette\LiveSearch;
use ModernDashboard\Palette\SearchController;
use ModernDashboard\Palette\SearchIndex;
use ModernDashboard\Rest\BuilderRoutes;
use ModernDashboard\Rest\MenuRoutes;
use ModernDashboard\Rest\PaletteRoutes;
use ModernDashboard\Rest\ThemeRoutes;
use ModernDashboard\Rest\Routes;
use ModernDashboard\Settings\Settings;
use ModernDashboard\Theme\ThemeRenderer;
use ModernDashboard\Theme\ThemeRepository;
use ModernDashboard\Support\Capabilities;
use ModernDashboard\Support\Requirements;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	private string $file;
	private string $version;

	private ?Settings $settings            = null;
	private ?Store $store                  = null;
	private ?MetricsRepository $repo       = null;
	private ?NetworkAggregator $aggregate  = null;
	private ?BlockRegistry $registry       = null;
	private ?TemplateRepository $templates = null;
	private ?MenuCatalogue $catalogue      = null;
	private ?MenuRules $menu_rules         = null;
	private ?ThemeRepository $themes       = null;
	private ?CommandRegistry $commands     = null;
	private ?SearchIndex $search_index     = null;
	private ?PaletteGate $palette_gate     = null;

	public function __construct( string $file, string $version ) {
		$this->file    = $file;
		$this->version = $version;
	}

	public function file(): string {
		return $this->file;
	}

	public function version(): string {
		return $this->version;
	}

	public function dir(): string {
		return plugin_dir_path( $this->file );
	}

	public function url(): string {
		return plugin_dir_url( $this->file );
	}

	public function settings(): Settings {
		return $this->settings ??= new Settings();
	}

	public function store(): Store {
		return $this->store ??= new Store();
	}

	public function repository(): MetricsRepository {
		return $this->repo ??= new MetricsRepository( $this->store(), new SiteCollector( $this->settings() ) );
	}

	public function aggregator(): NetworkAggregator {
		return $this->aggregate ??= new NetworkAggregator( $this->repository() );
	}

	public function registry(): BlockRegistry {
		return $this->registry ??= new BlockRegistry();
	}

	public function catalogue(): MenuCatalogue {
		return $this->catalogue ??= new MenuCatalogue();
	}

	public function menu_rules(): MenuRules {
		return $this->menu_rules ??= new MenuRules();
	}

	public function themes(): ThemeRepository {
		return $this->themes ??= new ThemeRepository();
	}

	public function commands(): CommandRegistry {
		return $this->commands ??= new CommandRegistry();
	}

	public function search_index(): SearchIndex {
		return $this->search_index ??= new SearchIndex();
	}

	public function palette_gate(): PaletteGate {
		return $this->palette_gate ??= new PaletteGate( $this->settings() );
	}

	public function templates(): TemplateRepository {
		return $this->templates ??= new TemplateRepository(
			$this->registry(),
			new LayoutSanitizer( $this->registry() )
		);
	}

	/**
	 * Wire everything up. Bails early (with a notice) when requirements fail so a
	 * mismatched environment degrades to an explanation rather than a fatal.
	 */
	public function boot(): void {
		$requirements = new Requirements( $this->file );

		if ( ! $requirements->met() ) {
			$requirements->render_notice();

			return;
		}

		add_action( 'init', array( Capabilities::class, 'register' ) );

		( new Scheduler( $this->repository(), $this->settings() ) )->register();
		( new Routes( $this->repository(), $this->aggregator(), $this->settings() ) )->register();
		( new BuilderRoutes( $this->registry(), $this->templates() ) )->register();
		( new MenuRoutes( $this->catalogue(), $this->menu_rules(), $this->templates() ) )->register();
		$this->catalogue()->register();
		( new MenuApplier( $this->menu_rules() ) )->register();
		( new ThemeRoutes( $this->themes() ) )->register();
		( new ThemeRenderer( $this->themes() ) )->register();
		( new NetworkAdminPage() )->register();

		$palette_gate = $this->palette_gate();
		$palette_gate->register();

		( new PaletteRoutes(
			new CommandResolver( $this->commands() ),
			new SearchController( $this->search_index(), new LiveSearch(), $this->repository() )
		) )->register();

		// Registers unconditionally and checks the setting when the hook fires,
		// so switching the palette on does not wait for a request that happens
		// to re-register hooks.
		( new IndexBuilder( $this->search_index(), $this->settings() ) )->register();

		( new Assets( $this, $palette_gate ) )->register();

		// Deliberately on `init`: loading a textdomain earlier makes WordPress
		// complain about just-in-time translation loading.
		add_action(
			'init',
			function (): void {
				load_plugin_textdomain(
					'modern-dashboard',
					false,
					dirname( plugin_basename( $this->file ) ) . '/languages'
				);
			}
		);
	}

	/**
	 * Activation. Only meaningful when network-activated on a multisite install.
	 *
	 * @param bool $network_wide Whether the plugin is being network-activated.
	 */
	public static function on_activate( bool $network_wide = false ): void {
		if ( ! is_multisite() || ! $network_wide ) {
			// Nothing to set up; boot() surfaces the reason in the admin.
			return;
		}

		$settings = new Settings();
		$settings->all(); // Persists defaults on first read.

		Scheduler::schedule( $settings->get( 'refresh_interval' ) );
	}

	public static function on_deactivate(): void {
		Scheduler::unschedule();
	}
}
