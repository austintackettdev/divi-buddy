<?php
/**
 * Main plugin bootstrap.
 *
 * @package DiviBuddy
 */

namespace DiviBuddy;

use DiviBuddy\Admin\SettingsPage;
use DiviBuddy\Divi\Divi;
use DiviBuddy\Divi\VariablesSync;
use DiviBuddy\Frontend\CssOutput;
use DiviBuddy\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Wires up all modules.
 */
final class Plugin {

	public const CAPABILITY = 'manage_divi_buddy';

	/** Option holding the installed version, used to detect plugin updates. */
	public const VERSION_OPTION = 'dbuddy_version';

	private static ?Plugin $instance = null;

	public static function instance(): Plugin {
		return self::$instance ??= new self();
	}

	/**
	 * Register hooks. Divi 5 bootstraps on `init` priority 0, so we check for it after that.
	 */
	public function boot(): void {
		// Updates work even when Divi is missing, so a fix can always be installed.
		( new Updater() )->register();

		add_action( 'init', [ $this, 'init' ], 5 );
	}

	public function init(): void {
		load_plugin_textdomain( 'divi-buddy', false, dirname( plugin_basename( DBUDDY_FILE ) ) . '/languages' );

		if ( ! Divi::is_available() ) {
			add_action( 'admin_notices', [ $this, 'missing_divi_notice' ] );
			return;
		}

		$this->maybe_upgrade();

		$sync = new VariablesSync();
		$sync->register();

		( new CssOutput() )->register();

		if ( is_admin() ) {
			( new SettingsPage( $sync ) )->register();
		}
	}

	/**
	 * On first run or after a plugin update, flag that Divi needs a sync.
	 */
	private function maybe_upgrade(): void {
		if ( get_option( self::VERSION_OPTION ) === DBUDDY_VERSION ) {
			return;
		}
		self::grant_capability();
		update_option( self::VERSION_OPTION, DBUDDY_VERSION, false );
		update_option( VariablesSync::NEEDS_SYNC_OPTION, 1, false );
	}

	public static function activate(): void {
		self::grant_capability();
		update_option( VariablesSync::NEEDS_SYNC_OPTION, 1, false );
	}

	/**
	 * Only Administrators get access (super admins pass all capability checks on multisite).
	 */
	private static function grant_capability(): void {
		$role = get_role( 'administrator' );
		if ( $role && ! $role->has_cap( self::CAPABILITY ) ) {
			$role->add_cap( self::CAPABILITY );
		}
	}

	public function missing_divi_notice(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		printf(
			'<div class="notice notice-warning"><p>%s</p></div>',
			esc_html__( 'Divi Buddy needs the Divi 5 theme to be active. It is switched off until Divi 5 is installed and active.', 'divi-buddy' )
		);
	}

	/**
	 * Convenience accessor used across modules.
	 */
	public static function settings(): Settings {
		static $settings = null;
		return $settings ??= new Settings();
	}
}
