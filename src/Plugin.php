<?php
/**
 * Plugin bootstrap and tiny service container.
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain;

use ReviewCaptain\Audit\AuditLog;
use ReviewCaptain\Events\Events;
use ReviewCaptain\Install\Installer;
use ReviewCaptain\Repository\ReviewRepository;
use ReviewCaptain\Settings\Settings;

/**
 * Central plugin object.
 */
final class Plugin {

	/**
	 * Feature modules. Missing classes are skipped so the core never fatals.
	 *
	 * @var list<class-string<Module>>
	 */
	private const MODULES = array(
		\ReviewCaptain\Jobs\JobsModule::class,
		\ReviewCaptain\Providers\ProvidersModule::class,
		\ReviewCaptain\Connectors\ConnectorsModule::class,
		\ReviewCaptain\Privacy\PrivacyModule::class,
		\ReviewCaptain\ImportExport\ImportExportModule::class,
		\ReviewCaptain\Frontend\FrontendModule::class,
		\ReviewCaptain\Seo\SeoModule::class,
		\ReviewCaptain\Admin\AdminModule::class,
		\ReviewCaptain\Cli\CliModule::class,
	);

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Lazily created services.
	 *
	 * @var array<string, object>
	 */
	private array $services = array();

	/**
	 * Service factories.
	 *
	 * @var array<string, callable(Plugin): object>
	 */
	private array $factories = array();

	/**
	 * Registered modules.
	 *
	 * @var array<class-string, Module>
	 */
	private array $modules = array();

	/**
	 * Boot on plugins_loaded.
	 */
	public static function boot(): void {
		if ( null !== self::$instance ) {
			return;
		}
		self::$instance = new self();
		self::$instance->init();
	}

	/**
	 * Get the instance (boots if needed).
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Reset (tests only).
	 */
	public static function reset_for_tests(): void {
		self::$instance = null;
	}

	/**
	 * Constructor registers core services.
	 */
	private function __construct() {
		$this->factory( 'settings', static fn() => new Settings() );
		$this->factory( 'reviews', static fn() => new ReviewRepository() );
		$this->factory( 'audit', static fn() => new AuditLog() );
		$this->factory( 'events', static fn() => new Events() );
	}

	/**
	 * Initialise hooks and modules.
	 */
	private function init(): void {
		add_action( 'init', array( $this, 'load_textdomain' ), 0 );
		add_action( 'admin_init', array( Installer::class, 'maybe_upgrade' ) );
		add_action( 'wp_initialize_site', array( Installer::class, 'on_new_site' ), 20 );

		foreach ( self::MODULES as $class ) {
			if ( class_exists( $class ) ) {
				$module = new $class();
				if ( $module instanceof Module ) {
					$module->register( $this );
					$this->modules[ $class ] = $module;
				}
			}
		}

		/**
		 * Fires when Review Captain core is ready. Pro add-on and connectors hook in here.
		 *
		 * @param Plugin $plugin Plugin instance.
		 */
		do_action( 'review_captain_loaded', $this );
	}

	/**
	 * Load translations.
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain( 'review-captain', false, dirname( plugin_basename( REVIEW_CAPTAIN_FILE ) ) . '/languages' );
	}

	/**
	 * Register a service factory.
	 *
	 * @param string                  $id      Service ID.
	 * @param callable(Plugin):object $factory Factory.
	 */
	public function factory( string $id, callable $factory ): void {
		$this->factories[ $id ] = $factory;
		unset( $this->services[ $id ] );
	}

	/**
	 * Get a service.
	 *
	 * @param string $id Service ID.
	 * @throws \OutOfBoundsException When unknown.
	 */
	public function get( string $id ): object {
		if ( ! isset( $this->services[ $id ] ) ) {
			if ( ! isset( $this->factories[ $id ] ) ) {
				throw new \OutOfBoundsException( 'Unknown Review Captain service: ' . $id ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}
			$this->services[ $id ] = ( $this->factories[ $id ] )( $this );
		}
		return $this->services[ $id ];
	}

	/**
	 * Whether a service exists.
	 *
	 * @param string $id Service ID.
	 */
	public function has( string $id ): bool {
		return isset( $this->factories[ $id ] );
	}

	/**
	 * Review repository.
	 */
	public function reviews(): ReviewRepository {
		/** @var ReviewRepository */
		return $this->get( 'reviews' );
	}

	/**
	 * Settings.
	 */
	public function settings(): Settings {
		/** @var Settings */
		return $this->get( 'settings' );
	}

	/**
	 * Audit log.
	 */
	public function audit(): AuditLog {
		/** @var AuditLog */
		return $this->get( 'audit' );
	}

	/**
	 * Event dispatcher.
	 */
	public function events(): Events {
		/** @var Events */
		return $this->get( 'events' );
	}

	/**
	 * Registered module by class.
	 *
	 * @param class-string $class Module class.
	 */
	public function module( string $class ): ?Module {
		return $this->modules[ $class ] ?? null;
	}
}
