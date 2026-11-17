<?php
namespace DiviBuddy\Tests\Unit;

use DiviBuddy\Library\BlockMarkup;
use DiviBuddy\Tests\TestCase;

final class BlockMarkupTest extends TestCase {

	/**
	 * Real Divi 5.19 markup captured from the dev site. Written with \\u escapes (in single quotes,
	 * PHP keeps them literal) because Divi stores <, > and " inside attribute JSON as \uXXXX escapes.
	 */
	private const REAL = '<!-- wp:divi/placeholder --><!-- wp:divi/section {"builderVersion":"5.19.0"} -->' . "\n"
		. '<!-- wp:divi/text {"content":{"innerContent":{"desktop":{"value":"\\u003ch2\\u003eHi\\u003c/h2\\u003e"}},"decoration":{"headingFont":{"h2":{"font":{"desktop":{"value":{"color":"$variable({\\u0022type\\u0022:\\u0022color\\u0022,\\u0022value\\u0022:{\\u0022name\\u0022:\\u0022gcid-j18rmkfenx\\u0022,\\u0022settings\\u0022:{}}})$"}}}}}}},"builderVersion":"5.19.0"} /-->' . "\n"
		. '<!-- /wp:divi/section --><!-- /wp:divi/placeholder -->';

	public function test_untouched_blocks_stay_byte_identical(): void {
		$this->assertSame( self::REAL, BlockMarkup::map_attributes( self::REAL, static fn() => null ) );
	}

	public function test_reencoding_matches_wordpress_serialization(): void {
		// Re-encoding unchanged attributes must reproduce Divi/WordPress's exact output.
		$this->assertSame( self::REAL, BlockMarkup::map_attributes( self::REAL, static fn( $name, $attrs ) => $attrs ) );
	}

	public function test_empty_objects_survive(): void {
		$out = BlockMarkup::map_attributes( '<!-- wp:divi/text {"a":{},"b":[]} /-->', static fn( $n, $a ) => $a );
		$this->assertSame( '<!-- wp:divi/text {"a":{},"b":[]} /-->', $out );
	}

	public function test_callback_receives_decoded_values(): void {
		$seen = [];
		BlockMarkup::map_attributes(
			self::REAL,
			static function ( string $name, object $attrs ) use ( &$seen ) {
				$seen[ $name ] = $attrs;
				return null;
			}
		);
		$this->assertSame( [ 'divi/section', 'divi/text' ], array_keys( $seen ) );
		$this->assertSame( '<h2>Hi</h2>', $seen['divi/text']->content->innerContent->desktop->value );
	}

	public function test_unreadable_json_is_reported_and_left_alone(): void {
		$errors = [];
		$broken = '<!-- wp:divi/text {"a": nope} /-->';
		$this->assertSame( $broken, BlockMarkup::map_attributes( $broken, static fn( $n, $a ) => $a, $errors ) );
		$this->assertCount( 1, $errors );
	}

	public function test_block_names(): void {
		$this->assertSame( [ 'divi/placeholder', 'divi/section', 'divi/text' ], BlockMarkup::block_names( self::REAL ) );
	}
}
