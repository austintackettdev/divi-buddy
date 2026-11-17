<?php
/**
 * Minimal reader/writer for Divi 5 block markup (`<!-- wp:divi/name {json} -->`). Pure PHP, no
 * WordPress needed, so the converter can run from the command line and in unit tests.
 *
 * Attribute JSON is decoded to stdClass objects (not arrays) so `{}` and `[]` survive a round trip.
 *
 * @package DiviBuddy
 */

namespace DiviBuddy\Library;

defined( 'ABSPATH' ) || exit;

/**
 * Block markup helpers.
 */
final class BlockMarkup {

	/**
	 * Matches a block opener/void tag that has attributes. WordPress escapes `--`, `<` and `>` inside
	 * attribute JSON, so `-->` can never appear inside the JSON and the lazy match is safe.
	 */
	private const BLOCK_WITH_ATTRS = '/<!--\s+wp:([a-z][a-z0-9_-]*\/[a-z][a-z0-9_-]*)\s+(\{.*?\})\s+(\/?)-->/s';

	/**
	 * Run $callback on every block's attributes. When the callback returns an object, that block's
	 * attribute JSON is re-encoded; when it returns null, the block is left byte-for-byte untouched.
	 *
	 * @param string   $content  Block markup.
	 * @param callable $callback fn( string $block_name, object $attrs ): ?object
	 * @param string[] $errors   Receives a message for any block whose JSON can't be parsed.
	 */
	public static function map_attributes( string $content, callable $callback, array &$errors = [] ): string {
		return (string) preg_replace_callback(
			self::BLOCK_WITH_ATTRS,
			static function ( array $m ) use ( $callback, &$errors ): string {
				$attrs = json_decode( $m[2] );
				if ( ! is_object( $attrs ) ) {
					$errors[] = sprintf( 'A "%s" block has settings that could not be read.', $m[1] );
					return $m[0];
				}
				$new = $callback( $m[1], $attrs );
				if ( null === $new ) {
					return $m[0];
				}
				return '<!-- wp:' . $m[1] . ' ' . self::encode_attributes( $new ) . ' ' . ( '/' === $m[3] ? '/' : '' ) . '-->';
			},
			$content
		);
	}

	/**
	 * Names of all blocks in the markup (openers and void blocks).
	 *
	 * @return string[]
	 */
	public static function block_names( string $content ): array {
		preg_match_all( '/<!--\s+wp:([a-z][a-z0-9_-]*\/[a-z][a-z0-9_-]*)[\s\/]/', $content, $m );
		return $m[1];
	}

	/**
	 * Encode attributes exactly like WordPress's serialize_block_attributes().
	 */
	public static function encode_attributes( object $attrs ): string {
		$json = (string) json_encode( $attrs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION );
		$json = str_replace( '--', '\\u002d\\u002d', $json );
		$json = str_replace( '<', '\\u003c', $json );
		$json = str_replace( '>', '\\u003e', $json );
		$json = str_replace( '&', '\\u0026', $json );
		// Escaped quotes inside strings: \" → " (matches WordPress).
		return str_replace( '\\"', '\\u0022', $json );
	}
}
