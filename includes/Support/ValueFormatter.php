<?php
/**
 * Converts stored setting values into CSS values (used by both the CSS output and the Divi sync).
 *
 * @package DiviBuddy
 */

namespace DiviBuddy\Support;

use DiviBuddy\Settings\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Setting value → CSS value.
 */
final class ValueFormatter {

	/**
	 * @param array $field Schema field.
	 * @param mixed $value Sanitized stored value.
	 */
	public static function css( array $field, mixed $value ): string {
		switch ( $field['type'] ) {
			case 'color':
			case 'select':
				return (string) $value;

			case 'font':
				return self::font_stack( (string) $value );

			case 'number':
				return FluidSize::num( (float) $value ) . ( $field['unit'] ?? '' );

			case 'fluid':
				$value = is_array( $value ) ? $value : $field['default'];
				return FluidSize::clamp(
					(float) $value['mobile'],
					(float) $value['desktop'],
					Schema::FLUID_MIN_VIEWPORT,
					Schema::FLUID_MAX_VIEWPORT
				);
		}
		return '';
	}

	/**
	 * Font family with a generic fallback, e.g. `'Open Sans', sans-serif`.
	 * Font names are restricted to [A-Za-z0-9 -] by the sanitizer, so quoting is safe.
	 */
	public static function font_stack( string $name ): string {
		$name = preg_replace( '/[^A-Za-z0-9 \-]/', '', $name );
		return "'" . $name . "', sans-serif";
	}
}
