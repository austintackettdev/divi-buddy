<?php
/**
 * Pushes Divi Buddy settings into Divi 5's global colors, fonts and variables.
 *
 * Only Divi's public GlobalData API is used (never raw option writes), and only Divi Buddy's own
 * items (gcid-dbuddy-*, gvid-dbuddy-*) plus Divi's 5 built-in colors and 2 built-in fonts are changed.
 * See docs/divi5-notes.md.
 *
 * @package DiviBuddy
 */

namespace DiviBuddy\Divi;

use DiviBuddy\Plugin;
use DiviBuddy\Settings\Schema;
use DiviBuddy\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Divi sync.
 */
final class VariablesSync {

	public const NEEDS_SYNC_OPTION = 'dbuddy_needs_sync';
	public const STATUS_OPTION     = 'dbuddy_sync_status';
	public const BACKUP_OPTION     = 'dbuddy_divi_backup';

	public function register(): void {
		add_action( 'add_option_' . Settings::OPTION, [ $this, 'on_settings_saved' ] );
		add_action( 'update_option_' . Settings::OPTION, [ $this, 'on_settings_saved' ] );
		add_action( 'admin_init', [ $this, 'maybe_run_pending' ] );
	}

	public function on_settings_saved(): void {
		$this->run();
	}

	/**
	 * Run a sync that was flagged on activation or after a plugin update.
	 */
	public function maybe_run_pending(): void {
		if ( ! get_option( self::NEEDS_SYNC_OPTION ) || wp_doing_ajax() || ! current_user_can( Plugin::CAPABILITY ) ) {
			return;
		}
		$status = $this->run();
		if ( $status['ok'] ) {
			delete_option( self::NEEDS_SYNC_OPTION );
		}
	}

	/**
	 * Send the current settings to Divi.
	 *
	 * Must run as a logged-in user who can `edit_theme_options` (Divi refuses variable saves otherwise).
	 *
	 * @return array{ok: bool, time: int, message: string, divi_version: string}
	 */
	public function run(): array {
		if ( ! Divi::is_available() ) {
			return $this->record( false, __( 'Divi 5 could not be found, so nothing was sent to Divi.', 'divi-buddy' ) );
		}
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return $this->record( false, __( 'Your account is not allowed to change Divi\'s global settings.', 'divi-buddy' ) );
		}

		$gd = Divi::GLOBAL_DATA_CLASS;

		try {
			$this->maybe_backup();

			$payload = PayloadBuilder::build(
				Schema::fields(),
				Plugin::settings()->values(),
				gmdate( 'Y-m-d\TH:i:s.000\Z' )
			);

			// 1. Global colors (merge mode: `true` = already sanitized → Divi merges instead of replacing).
			$colors = call_user_func( [ $gd, 'sanitize_global_colors_data' ], $payload['colors'] );
			call_user_func( [ $gd, 'set_global_colors' ], $colors, true );

			// 2. Divi's two built-in fonts live in its theme options.
			$fonts_changed = false;
			foreach ( $payload['fonts'] as $option => $font ) {
				if ( et_get_option( $option, 'none' ) !== $font ) {
					et_update_option( $option, $font );
					$fonts_changed = true;
				}
			}
			if ( $fonts_changed ) {
				$this->clear_divi_css_cache();
			}

			// 3. Global variables (upsert onto existing ones; Divi validates and locks).
			$error = null;
			call_user_func_array( [ $gd, 'import_global_variables' ], [ $payload['variables'], &$error ] );
			if ( is_wp_error( $error ) ) {
				/* translators: %s: technical error message from Divi. */
				return $this->record( false, sprintf( __( 'Divi rejected the variables: %s', 'divi-buddy' ), $error->get_error_message() ) );
			}

			$missing = $this->verify( $payload );
			if ( $missing ) {
				return $this->record(
					false,
					/* translators: %s: comma-separated list of IDs. */
					sprintf( __( 'Some settings did not reach Divi: %s. Check that your role is allowed to manage variables in Divi → Role Editor.', 'divi-buddy' ), implode( ', ', $missing ) )
				);
			}
		} catch ( \Throwable $e ) {
			/* translators: %s: technical error message. */
			return $this->record( false, sprintf( __( 'Something went wrong while sending settings to Divi: %s', 'divi-buddy' ), $e->getMessage() ) );
		}

		return $this->record( true, __( 'Your settings are saved and Divi has been updated.', 'divi-buddy' ) );
	}

	/**
	 * Read back from Divi and return IDs whose value didn't stick.
	 *
	 * @return string[]
	 */
	private function verify( array $payload ): array {
		$gd        = Divi::GLOBAL_DATA_CLASS;
		$colors    = call_user_func( [ $gd, 'get_global_colors' ] );
		$variables = json_decode( wp_json_encode( call_user_func( [ $gd, 'get_global_variables' ] ) ), true );
		$missing   = [];

		// Divi caches its built-in colors in a static for the request, so read those from theme options.
		$builtin = property_exists( $gd, 'customizer_colors' ) ? $gd::$customizer_colors : [];

		foreach ( $payload['colors'] as $id => $data ) {
			$stored = isset( $builtin[ $id ]['option_name'] )
				? et_get_option( $builtin[ $id ]['option_name'], '' )
				: ( $colors[ $id ]['color'] ?? '' );
			if ( strtolower( (string) $stored ) !== strtolower( $data['color'] ) ) {
				$missing[] = $id;
			}
		}
		foreach ( $payload['variables'] as $variable ) {
			if ( ( $variables[ $variable['type'] ][ $variable['id'] ]['value'] ?? null ) !== $variable['value'] ) {
				$missing[] = $variable['id'];
			}
		}
		return $missing;
	}

	/**
	 * Keep a one-time copy of Divi's global data from before Divi Buddy first touched it.
	 */
	private function maybe_backup(): void {
		if ( false !== get_option( self::BACKUP_OPTION ) ) {
			return;
		}
		$gd     = Divi::GLOBAL_DATA_CLASS;
		$backup = [
			'time'          => time(),
			'divi_version'  => Divi::version(),
			'global_colors' => call_user_func( [ $gd, 'get_global_colors' ] ),
			'variables'     => json_decode( wp_json_encode( call_user_func( [ $gd, 'get_global_variables' ] ) ), true ),
			'theme_options' => [],
		];
		foreach ( [ 'accent_color', 'secondary_accent_color', 'header_color', 'font_color', 'link_color', 'heading_font', 'body_font' ] as $option ) {
			$backup['theme_options'][ $option ] = et_get_option( $option, null );
		}
		add_option( self::BACKUP_OPTION, $backup, '', false );
	}

	private function clear_divi_css_cache(): void {
		if ( class_exists( 'ET_Core_PageResource' ) && method_exists( 'ET_Core_PageResource', 'remove_static_resources' ) ) {
			\ET_Core_PageResource::remove_static_resources( 'all', 'all', true );
		}
	}

	private function record( bool $ok, string $message ): array {
		$status = [
			'ok'           => $ok,
			'time'         => time(),
			'message'      => $message,
			'divi_version' => Divi::is_available() ? Divi::version() : '',
		];
		update_option( self::STATUS_OPTION, $status, false );
		return $status;
	}

	/**
	 * @return array{ok: bool, time: int, message: string, divi_version: string}|null
	 */
	public static function last_status(): ?array {
		$status = get_option( self::STATUS_OPTION );
		return is_array( $status ) ? $status : null;
	}
}
