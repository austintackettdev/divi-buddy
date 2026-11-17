<?php
/**
 * Turns a desktop/mobile pixel pair into a CSS clamp() value that scales smoothly with screen width.
 *
 * @package DiviBuddy
 */

namespace DiviBuddy\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Fluid size calculator.
 */
final class FluidSize {

	private const ROOT_FONT_SIZE = 16;

	/**
	 * @param float $mobile       Size in px at (and below) $min_viewport.
	 * @param float $desktop      Size in px at (and above) $max_viewport.
	 * @param int   $min_viewport Viewport width in px where scaling starts.
	 * @param int   $max_viewport Viewport width in px where scaling stops.
	 *
	 * @return string e.g. "clamp(2.25rem, 1.467rem + 3.478vw, 3.5rem)", or "1rem" when both sizes match.
	 */
	public static function clamp( float $mobile, float $desktop, int $min_viewport, int $max_viewport ): string {
		if ( abs( $desktop - $mobile ) < 0.001 || $max_viewport <= $min_viewport ) {
			return self::rem( $desktop );
		}

		$slope     = ( $desktop - $mobile ) / ( $max_viewport - $min_viewport );
		$intercept = $mobile - $slope * $min_viewport;

		$preferred = sprintf(
			'%s %s %svw',
			self::rem( $intercept ),
			$slope < 0 ? '-' : '+',
			self::num( abs( $slope * 100 ) )
		);

		$lower = self::rem( min( $mobile, $desktop ) );
		$upper = self::rem( max( $mobile, $desktop ) );

		return sprintf( 'clamp(%s, %s, %s)', $lower, $preferred, $upper );
	}

	public static function rem( float $px ): string {
		return self::num( $px / self::ROOT_FONT_SIZE ) . 'rem';
	}

	/**
	 * Format a number with up to 3 decimals and no trailing zeros.
	 */
	public static function num( float $value ): string {
		$formatted = rtrim( rtrim( number_format( $value, 3, '.', '' ), '0' ), '.' );
		return '-0' === $formatted ? '0' : $formatted;
	}
}
