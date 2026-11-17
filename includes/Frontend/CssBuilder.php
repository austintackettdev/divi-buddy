<?php
/**
 * Builds the front-end CSS: `--dbuddy-*` custom properties on :root, plus site-wide text rules.
 * Pure logic so it can be unit tested.
 *
 * @package DiviBuddy
 */

namespace DiviBuddy\Frontend;

use DiviBuddy\Support\ValueFormatter;

defined( 'ABSPATH' ) || exit;

/**
 * Settings → CSS.
 */
final class CssBuilder {

	/**
	 * @param array<string, array> $fields       Schema fields.
	 * @param array<string, mixed> $values       Sanitized values.
	 * @param bool                 $apply_styles Also apply sizes/weights/line heights to text site-wide.
	 */
	public static function build( array $fields, array $values, bool $apply_styles = true ): string {
		$vars = [];
		foreach ( $fields as $key => $field ) {
			if ( empty( $field['css'] ) ) {
				continue;
			}
			$value = ValueFormatter::css( $field, $values[ $key ] ?? $field['default'] );
			if ( '' === $value || preg_match( '/[;{}<>]/', $value ) ) {
				continue; // Never print anything that could break out of the declaration.
			}
			$vars[] = $field['css'] . ':' . $value . ';';
		}

		$css = ':root{' . implode( '', $vars ) . '}';

		if ( $apply_styles ) {
			$css .= self::site_rules( $fields );
		}

		return $css;
	}

	/**
	 * Rules that make the text settings take effect site-wide. `body h1` (specificity 0,0,2) beats
	 * Divi's theme-level `h1` rules but loses to anything set on an individual module in the builder.
	 */
	private static function site_rules( array $fields ): string {
		$var = static function ( string $apply ) use ( $fields ): ?string {
			foreach ( $fields as $field ) {
				if ( ( $field['apply'] ?? '' ) === $apply && ! empty( $field['css'] ) ) {
					return 'var(' . $field['css'] . ')';
				}
			}
			return null;
		};

		$rules = [];

		$body = array_filter(
			[
				'font-size'   => $var( 'size_body' ),
				'line-height' => $var( 'body_line_height' ),
				'font-weight' => $var( 'body_weight' ),
			]
		);
		if ( $body ) {
			$rules[] = 'body,body p' . self::declarations( $body );
		}

		$headings = array_filter(
			[
				'line-height' => $var( 'heading_line_height' ),
				'font-weight' => $var( 'heading_weight' ),
			]
		);
		if ( $headings ) {
			$rules[] = 'body h1,body h2,body h3,body h4,body h5,body h6' . self::declarations( $headings );
		}

		foreach ( [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ] as $tag ) {
			$size = $var( 'size_' . $tag );
			if ( $size ) {
				$rules[] = 'body ' . $tag . self::declarations( [ 'font-size' => $size ] );
			}
		}

		$small = $var( 'size_small' );
		if ( $small ) {
			$rules[] = 'body small' . self::declarations( [ 'font-size' => $small ] );
		}

		return implode( '', $rules );
	}

	private static function declarations( array $props ): string {
		$out = '';
		foreach ( $props as $prop => $value ) {
			$out .= $prop . ':' . $value . ';';
		}
		return '{' . $out . '}';
	}
}
