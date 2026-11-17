<?php
namespace DiviBuddy\Tests\Unit;

use DiviBuddy\Settings\Sanitizer;
use DiviBuddy\Settings\Schema;
use DiviBuddy\Tests\TestCase;

final class SanitizerTest extends TestCase {

	/**
	 * @dataProvider colors
	 */
	public function test_color( mixed $input, ?string $expected ): void {
		$this->assertSame( $expected, Sanitizer::color( $input ) );
	}

	public static function colors(): array {
		return [
			'short hex'          => [ '#ABC', '#abc' ],
			'long hex'           => [ '#2EA3F2', '#2ea3f2' ],
			'with alpha'         => [ '#2ea3f280', '#2ea3f280' ],
			'missing hash'       => [ 'fff', '#fff' ],
			'whitespace'         => [ '  #fff ', '#fff' ],
			'named color'        => [ 'red', null ],
			'wrong length'       => [ '#12345', null ],
			'css injection'      => [ '#fff;}body{display:none', null ],
			'empty'              => [ '', null ],
			'array'              => [ [ '#fff' ], null ],
		];
	}

	public function test_number_is_clamped_to_range(): void {
		$field = [ 'min' => 0, 'max' => 100 ];
		$this->assertSame( 100, Sanitizer::number( '250', $field ) );
		$this->assertSame( 0, Sanitizer::number( -5, $field ) );
		$this->assertSame( 1.25, Sanitizer::number( '1.25', $field ) );
		$this->assertSame( 12, Sanitizer::number( ' 12 ', $field ) );
	}

	public function test_number_rejects_non_numbers(): void {
		$this->assertNull( Sanitizer::number( '12px', [] ) );
		$this->assertNull( Sanitizer::number( 'abc', [] ) );
		$this->assertNull( Sanitizer::number( null, [] ) );
		$this->assertNull( Sanitizer::number( [], [] ) );
	}

	public function test_fluid_requires_both_parts(): void {
		$field = [ 'min' => 0, 'max' => 200 ];
		$this->assertSame( [ 'desktop' => 56, 'mobile' => 36 ], Sanitizer::fluid( [ 'desktop' => '56', 'mobile' => '36' ], $field ) );
		$this->assertNull( Sanitizer::fluid( [ 'desktop' => '56' ], $field ) );
		$this->assertNull( Sanitizer::fluid( '56', $field ) );
	}

	public function test_select_only_allows_listed_options(): void {
		$this->assertSame( '700', Sanitizer::select( 700, [ '400', '700' ] ) );
		$this->assertNull( Sanitizer::select( '650', [ '400', '700' ] ) );
	}

	public function test_font_with_allow_list(): void {
		$sanitizer = new Sanitizer( [], [ 'Open Sans', 'Roboto' ] );
		$this->assertSame( 'Roboto', $sanitizer->font( 'Roboto' ) );
		$this->assertNull( $sanitizer->font( 'Comic Neue' ) );
	}

	public function test_font_without_allow_list_rejects_unsafe_names(): void {
		$sanitizer = new Sanitizer( [], null );
		$this->assertSame( 'Comic Neue', $sanitizer->font( 'Comic Neue' ) );
		$this->assertNull( $sanitizer->font( "Arial'; } body { display:none" ) );
		$this->assertNull( $sanitizer->font( '' ) );
	}

	public function test_invalid_value_keeps_previous_and_reports_error(): void {
		$sanitizer = new Sanitizer( Schema::fields(), null );
		$current   = [ 'color_primary' => '#123456' ];

		$result = $sanitizer->sanitize( [ 'color_primary' => 'not-a-color' ], $current );

		$this->assertSame( '#123456', $result['values']['color_primary'] );
		$this->assertCount( 1, $result['errors'] );
		$this->assertSame( 'color_primary', $result['errors'][0]['field'] );
	}

	public function test_missing_values_fall_back_to_current_then_default(): void {
		$sanitizer = new Sanitizer( Schema::fields(), null );
		$result    = $sanitizer->sanitize( [], [ 'color_primary' => '#123456' ] );

		$this->assertSame( '#123456', $result['values']['color_primary'] );
		$this->assertSame( Schema::defaults()['color_accent'], $result['values']['color_accent'] );
		$this->assertSame( [], $result['errors'] );
	}

	public function test_output_contains_only_schema_keys(): void {
		$sanitizer = new Sanitizer( Schema::fields(), null );
		$result    = $sanitizer->sanitize( [ 'evil' => '<script>' ], [] );

		$this->assertArrayNotHasKey( 'evil', $result['values'] );
		$this->assertSame( array_keys( Schema::fields() ), array_keys( $result['values'] ) );
	}
}
