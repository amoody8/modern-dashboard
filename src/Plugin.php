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
use ModernDashboard\Rest\BuilderRoutes;
use ModernDashboard\Rest\Routes;
use ModernDashboard\Settings\Settings;
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
		( new NetworkAdminPage() )->register();
		( new Assets( $this ) )->register();

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
