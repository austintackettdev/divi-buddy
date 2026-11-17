<?php
/**
 * Prints the Divi Buddy CSS in the page <head>.
 *
 * @package DiviBuddy
 */

namespace DiviBuddy\Frontend;

use DiviBuddy\Plugin;
use DiviBuddy\Settings\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Front-end CSS output.
 */
final class CssOutput {

	public function register(): void {
		add_action( 'wp_head', [ $this, 'print_css' ], 20 );
	}

	public function print_css(): void {
		/**
		 * Whether Divi Buddy applies its text sizes, weights and line heights to the whole site.
		 * When false, only the `--dbuddy-*` CSS variables are printed.
		 *
		 * @param bool $apply Default true.
		 */
		$apply = (bool) apply_filters( 'dbuddy_apply_site_styles', true );

		$css = CssBuilder::build( Schema::fields(), Plugin::settings()->values(), $apply );

		// CssBuilder only emits sanitized values and drops anything containing ; { } < >.
		echo '<style id="divi-buddy-css">' . $css . '</style>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
