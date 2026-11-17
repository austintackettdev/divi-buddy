<?php
/**
 * Thin, defensive wrapper around Divi 5 internals. Everything that touches Divi's code goes
 * through here or VariablesSync, so a Divi update can only break these two files.
 *
 * See docs/divi5-notes.md for what each call does and why.
 *
 * @package DiviBuddy
 */

namespace DiviBuddy\Divi;

use DiviBuddy\Settings\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Divi 5 helpers.
 */
final class Divi {

	public const GLOBAL_DATA_CLASS = '\ET\Builder\Packages\GlobalData\GlobalData';

	/**
	 * Divi 5 is active and exposes the API we rely on.
	 */
	public static function is_available(): bool {
		return class_exists( self::GLOBAL_DATA_CLASS )
			&& method_exists( self::GLOBAL_DATA_CLASS, 'import_global_variables' )
			&& method_exists( self::GLOBAL_DATA_CLASS, 'set_global_colors' )
			&& method_exists( self::GLOBAL_DATA_CLASS, 'sanitize_global_colors_data' )
			&& function_exists( 'et_get_option' )
			&& function_exists( 'et_update_option' );
	}

	public static function version(): string {
		$theme = wp_get_theme( get_template() );
		return (string) $theme->get( 'Version' );
	}

	/**
	 * Current values of Divi's built-in colors and fonts, keyed by Divi Buddy field key.
	 * Used as defaults before the first save so activating the plugin changes nothing visible.
	 *
	 * @return array<string, string>
	 */
	public static function current_builtin_values(): array {
		if ( ! self::is_available() ) {
			return [];
		}

		$values = [];
		$colors = call_user_func( [ self::GLOBAL_DATA_CLASS, 'get_global_colors' ] );

		foreach ( Schema::fields() as $key => $field ) {
			$divi = $field['divi'] ?? [];

			if ( 'customizer_color' === ( $divi['kind'] ?? '' ) ) {
				$color = $colors[ $divi['id'] ]['color'] ?? null;
				if ( is_string( $color ) && preg_match( '/^#[0-9a-fA-F]{3,8}$/', $color ) ) {
					$values[ $key ] = strtolower( $color );
				}
			}

			if ( 'customizer_font' === ( $divi['kind'] ?? '' ) ) {
				$font = et_get_option( $divi['option'], 'none' );
				if ( is_string( $font ) && '' !== $font && 'none' !== $font ) {
					$values[ $key ] = $font;
				}
			}
		}

		return $values;
	}

	/**
	 * All font names Divi offers (web-safe + Google), sorted. Null if Divi's font list isn't available.
	 *
	 * @return string[]|null
	 */
	public static function font_names(): ?array {
		if ( ! function_exists( 'et_builder_get_fonts' ) ) {
			return null;
		}
		$fonts = et_builder_get_fonts();
		if ( ! is_array( $fonts ) || empty( $fonts ) ) {
			return null;
		}
		$names = array_map( 'strval', array_keys( $fonts ) );
		sort( $names, SORT_NATURAL | SORT_FLAG_CASE );
		return $names;
	}
}
