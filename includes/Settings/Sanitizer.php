<?php
/**
 * Validates and cleans submitted settings. Pure logic (no database access) so it can be unit tested.
 *
 * Rule: a bad value never reaches the site. Invalid input keeps the previous value and reports an error.
 *
 * @package DiviBuddy
 */

namespace DiviBuddy\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Settings sanitizer.
 */
final class Sanitizer {

	/**
	 * @param array<string, array> $fields        Schema fields (Schema::fields()).
	 * @param string[]|null        $allowed_fonts Font names the user may pick, or null to accept any safe name.
	 */
	public function __construct(
		private array $fields,
		private ?array $allowed_fonts = null
	) {}

	/**
	 * Sanitize a full set of submitted values.
	 *
	 * @param array $input   Raw submitted values (key => value).
	 * @param array $current Current saved values, used when a submitted value is invalid or missing.
	 *
	 * @return array{values: array<string, mixed>, errors: array<int, array{field: string, message: string}>}
	 */
	public function sanitize( array $input, array $current ): array {
		$values = [];
		$errors = [];

		foreach ( $this->fields as $key => $field ) {
			$fallback = $current[ $key ] ?? $field['default'];

			if ( ! array_key_exists( $key, $input ) ) {
				$values[ $key ] = $fallback;
				continue;
			}

			$clean = $this->sanitize_field( $field, $input[ $key ] );

			if ( null === $clean ) {
				$values[ $key ] = $fallback;
				$errors[]       = [
					'field'   => $key,
					/* translators: %s: setting label. */
					'message' => sprintf( __( '"%s" had a value that could not be used, so the previous value was kept.', 'divi-buddy' ), $field['label'] ),
				];
				continue;
			}

			$values[ $key ] = $clean;
		}

		return [
			'values' => $values,
			'errors' => $errors,
		];
	}

	/**
	 * Sanitize one value. Returns null when the value is invalid.
	 */
	public function sanitize_field( array $field, mixed $value ): mixed {
		return match ( $field['type'] ) {
			'color'  => self::color( $value ),
			'font'   => $this->font( $value ),
			'select' => self::select( $value, array_keys( $field['options'] ?? [] ) ),
			'number' => self::number( $value, $field ),
			'fluid'  => self::fluid( $value, $field ),
			default  => null,
		};
	}

	/**
	 * Hex colors only (#rgb, #rrggbb, #rrggbbaa). Returned lowercase.
	 */
	public static function color( mixed $value ): ?string {
		if ( ! is_string( $value ) ) {
			return null;
		}
		$value = strtolower( trim( $value ) );
		if ( '' !== $value && '#' !== $value[0] ) {
			$value = '#' . $value;
		}
		return preg_match( '/^#([0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/', $value ) ? $value : null;
	}

	public function font( mixed $value ): ?string {
		if ( ! is_string( $value ) ) {
			return null;
		}
		$value = trim( $value );
		// Font family names: letters, numbers, spaces and hyphens only (keeps CSS output safe).
		if ( ! preg_match( '/^[A-Za-z0-9][A-Za-z0-9 \-]{0,79}$/', $value ) ) {
			return null;
		}
		if ( null !== $this->allowed_fonts && ! in_array( $value, $this->allowed_fonts, true ) ) {
			return null;
		}
		return $value;
	}

	public static function select( mixed $value, array $allowed ): ?string {
		$value = is_scalar( $value ) ? (string) $value : null;
		return ( null !== $value && in_array( $value, array_map( 'strval', $allowed ), true ) ) ? $value : null;
	}

	/**
	 * A number within the field's min/max. Out-of-range numbers are clamped; non-numbers are rejected.
	 */
	public static function number( mixed $value, array $field ): int|float|null {
		if ( is_string( $value ) ) {
			$value = trim( $value );
		}
		if ( ! is_numeric( $value ) ) {
			return null;
		}
		$number = (float) $value;
		if ( ! is_finite( $number ) ) {
			return null;
		}
		if ( isset( $field['min'] ) ) {
			$number = max( (float) $field['min'], $number );
		}
		if ( isset( $field['max'] ) ) {
			$number = min( (float) $field['max'], $number );
		}
		$number = round( $number, 3 );
		return floor( $number ) === $number ? (int) $number : $number;
	}

	/**
	 * A desktop/mobile pair of numbers.
	 *
	 * @return array{desktop: int|float, mobile: int|float}|null
	 */
	public static function fluid( mixed $value, array $field ): ?array {
		if ( ! is_array( $value ) ) {
			return null;
		}
		$desktop = self::number( $value['desktop'] ?? null, $field );
		$mobile  = self::number( $value['mobile'] ?? null, $field );
		if ( null === $desktop || null === $mobile ) {
			return null;
		}
		return [
			'desktop' => $desktop,
			'mobile'  => $mobile,
		];
	}
}
