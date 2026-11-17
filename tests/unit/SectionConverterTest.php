<?php
namespace DiviBuddy\Tests\Unit;

use DiviBuddy\Library\BlockMarkup;
use DiviBuddy\Library\Library;
use DiviBuddy\Library\SectionConverter;
use DiviBuddy\Tests\TestCase;

final class SectionConverterTest extends TestCase {

	private static function block( string $name, array $attrs, bool $void = true ): string {
		$attrs['builderVersion'] = '5.19.0';
		return '<!-- wp:' . $name . ' ' . BlockMarkup::encode_attributes( json_decode( json_encode( $attrs ) ) ) . ' ' . ( $void ? '/' : '' ) . '-->';
	}

	private static function wrap( string $inner, array $section_attrs = [] ): string {
		return '<!-- wp:divi/placeholder -->' . self::block( 'divi/section', $section_attrs, false ) . "\n" . $inner . "\n<!-- /wp:divi/section --><!-- /wp:divi/placeholder -->";
	}

	private static function variable( string $type, string $id ): string {
		return '$variable(' . json_encode( [ 'type' => $type, 'value' => [ 'name' => $id, 'settings' => new \stdClass() ] ] ) . ')$';
	}

	private static function export( string $content, string $type = 'section', string $title = 'Hero Split' ): array {
		return [
			'context' => 'et_builder_layouts',
			'data'    => [
				'20' => [
					'post_title'   => $title,
					'post_content' => $content,
					'terms'        => [ '4' => [ 'slug' => $type, 'taxonomy' => 'layout_type' ] ],
				],
			],
		];
	}

	public function test_images_become_placeholders_and_media_ids_are_dropped(): void {
		$content = self::wrap(
			self::block( 'divi/image', [ 'image' => [ 'innerContent' => [ 'desktop' => [ 'value' => [ 'src' => 'http://dev.local/wp-content/uploads/2026/11/photo.jpg', 'id' => '12', 'width' => '1920' ] ] ] ] ] ),
			[ 'module' => [ 'decoration' => [ 'background' => [ 'desktop' => [ 'value' => [ 'image' => [ 'url' => 'https://dev.local/wp-content/uploads/bg.webp?ver=2' ] ] ] ] ] ] ]
		);

		$result = ( new SectionConverter() )->convert_content( $content );

		$this->assertSame( [], $result['errors'] );
		$this->assertStringNotContainsString( 'dev.local', $result['content'] );
		$this->assertSame( 2, substr_count( $result['content'], SectionConverter::PLACEHOLDER_TOKEN ) );
		$this->assertStringNotContainsString( '"id":"12"', $result['content'] );
		$this->assertStringContainsString( '"width":"1920"', $result['content'] );
		$this->assertStringContainsString( '2 image(s)', implode( ' ', $result['warnings'] ) );
	}

	public function test_images_inside_rich_text_are_swapped(): void {
		$html    = '<p><img class="wp-image-12" src="http://dev.local/wp-content/uploads/a.png" alt="" /></p>';
		$content = self::wrap( self::block( 'divi/text', [ 'content' => [ 'innerContent' => [ 'desktop' => [ 'value' => $html ] ] ] ] ) );

		$result = ( new SectionConverter() )->convert_content( $content );

		$this->assertStringNotContainsString( 'a.png', $result['content'] );
		$this->assertStringContainsString( SectionConverter::PLACEHOLDER_TOKEN, $result['content'] );
	}

	public function test_divi_buddy_and_builtin_globals_are_allowed(): void {
		$content = self::wrap(
			self::block(
				'divi/text',
				[
					'module' => [ 'decoration' => [ 'spacing' => [ 'desktop' => [ 'value' => [ 'padding' => [ 'top' => self::variable( 'content', 'gvid-dbuddy-space-m' ) ] ] ] ] ] ],
					'title'  => [ 'decoration' => [ 'font' => [ 'font' => [ 'desktop' => [ 'value' => [ 'color' => self::variable( 'color', 'gcid-primary-color' ), 'size' => 'var(--gvid-dbuddy-size-h2)' ] ] ] ] ] ],
					'body'   => [ 'color' => self::variable( 'color', 'gcid-dbuddy-accent' ) ],
				]
			)
		);

		$result = ( new SectionConverter() )->convert_content( $content );

		$this->assertSame( [], $result['errors'] );
		$this->assertSame( $content, $result['content'], 'Nothing to change, so the markup must be untouched.' );
	}

	public function test_site_specific_globals_are_rejected(): void {
		$content = self::wrap(
			self::block( 'divi/text', [ 'a' => self::variable( 'content', 'gvid-1zerxt6x1m' ), 'b' => 'var(--gcid-j18rmkfenx)' ] )
		);

		$errors = implode( ' ', ( new SectionConverter() )->convert_content( $content )['errors'] );

		$this->assertStringContainsString( 'gvid-1zerxt6x1m', $errors );
		$this->assertStringContainsString( 'gcid-j18rmkfenx', $errors );
	}

	public function test_presets_are_rejected(): void {
		$content = self::wrap( self::block( 'divi/button', [ 'modulePreset' => [ 'ths6ikp4vc' ] ] ) );
		$this->assertStringContainsString( 'preset', implode( ' ', ( new SectionConverter() )->convert_content( $content )['errors'] ) );
	}

	public function test_empty_preset_values_are_fine(): void {
		$content = self::wrap( self::block( 'divi/button', [ 'modulePreset' => [] ] ) );
		$this->assertSame( [], ( new SectionConverter() )->convert_content( $content )['errors'] );
	}

	public function test_links_produce_warnings_not_errors(): void {
		$content = self::wrap( self::block( 'divi/button', [ 'button' => [ 'innerContent' => [ 'desktop' => [ 'value' => [ 'linkUrl' => 'https://dev.local/contact' ] ] ] ] ] ) );
		$result  = ( new SectionConverter() )->convert_content( $content );

		$this->assertSame( [], $result['errors'] );
		$this->assertStringContainsString( 'https://dev.local/contact', implode( ' ', $result['warnings'] ) );
	}

	public function test_windows_line_endings_are_normalized(): void {
		$content = str_replace( "\n", "\r\n", self::wrap( self::block( 'divi/text', [ 'a' => 'b' ] ) ) );
		$this->assertStringNotContainsString( "\r", ( new SectionConverter() )->convert_content( $content )['content'] );
	}

	public function test_convert_export_builds_library_sections(): void {
		$content = self::wrap( self::block( 'divi/text', [ 'content' => [ 'innerContent' => [ 'desktop' => [ 'value' => '<h2>Hello</h2>' ] ] ] ] ) );
		$result  = ( new SectionConverter() )->convert_export( self::export( $content, 'section', 'Hero — Split (Dark)' ) );

		$this->assertCount( 1, $result['sections'] );
		$section = $result['sections'][0];
		$this->assertSame( 'hero-split-dark', $section['id'] );
		$this->assertSame( 'Hero — Split (Dark)', $section['title'] );
		$this->assertSame( '5.19.0', $section['divi_version'] );

		// The written file must be readable by the library loader.
		$this->assertNotNull( Library::parse( Library::encode( $section ) ) );
	}

	public function test_non_sections_and_bad_files_are_rejected(): void {
		$converter = new SectionConverter();

		$row = $converter->convert_export( self::export( self::wrap( '' ), 'row' ) );
		$this->assertSame( [], $row['sections'] );
		$this->assertSame( 'error', $row['problems'][0]['level'] );

		$bad = $converter->convert_export( [ 'context' => 'epanel' ] );
		$this->assertSame( [], $bad['sections'] );
		$this->assertNotEmpty( $bad['problems'] );
	}

	public function test_sections_with_errors_are_not_returned(): void {
		$content = self::wrap( self::block( 'divi/button', [ 'modulePreset' => [ 'abc' ] ] ) );
		$this->assertSame( [], ( new SectionConverter() )->convert_export( self::export( $content ) )['sections'] );
	}

	/**
	 * @dataProvider titles
	 */
	public function test_slugify( string $title, string $expected ): void {
		$this->assertSame( $expected, SectionConverter::slugify( $title ) );
	}

	public static function titles(): array {
		return [
			[ 'Hero Split', 'hero-split' ],
			[ '  CTA / Banner #2 ', 'cta-banner-2' ],
			[ '***', 'section' ],
		];
	}
}
