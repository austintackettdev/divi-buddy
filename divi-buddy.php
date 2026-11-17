<?php
/**
 * Plugin Name:       Divi Buddy
 * Description:       Site-wide design settings (colors, fonts, sizes, spacing) for Divi 5, synced into Divi's Global Variables.
 * Version:           0.3.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            Divi Buddy
 * License:           GPL-2.0-or-later
 * Text Domain:       divi-buddy
 * Domain Path:       /languages
 * Update URI:        https://github.com/austintackettdev/divi-buddy
 *
 * @package DiviBuddy
 */

defined( 'ABSPATH' ) || exit;

define( 'DBUDDY_VERSION', '0.3.0' );
define( 'DBUDDY_FILE', __FILE__ );
define( 'DBUDDY_PATH', plugin_dir_path( __FILE__ ) );
define( 'DBUDDY_URL', plugin_dir_url( __FILE__ ) );

// Lightweight PSR-4 autoloader for the DiviBuddy\ namespace (no Composer needed at runtime).
spl_autoload_register(
	static function ( string $class ): void {
		$prefix = 'DiviBuddy\\';
		if ( ! str_starts_with( $class, $prefix ) ) {
			return;
		}
		$file = DBUDDY_PATH . 'includes/' . str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) ) . '.php';
		if ( is_readable( $file ) ) {
			require $file;
		}
	}
);

register_activation_hook( __FILE__, [ \DiviBuddy\Plugin::class, 'activate' ] );

\DiviBuddy\Plugin::instance()->boot();
