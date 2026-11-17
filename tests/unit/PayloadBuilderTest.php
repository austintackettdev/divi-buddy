<?php
namespace DiviBuddy\Tests\Unit;

use DiviBuddy\Divi\PayloadBuilder;
use DiviBuddy\Settings\Schema;
use DiviBuddy\Tests\TestCase;

final class PayloadBuilderTest extends TestCase {

	private const NOW = '2026-11-16T12:00:00.000Z';

	private function payload( array $overrides = [] ): array {
		return PayloadBuilder::build( Schema::fields(), array_merge( Schema::defaults(), $overrides ), self::NOW );
	}

	public function test_builtin_colors_only_carry_a_color(): void {
		$payload = $this->payload( [ 'color_primary' => '#ff0000' ] );
		$this->assertSame( [ 'color' => '#ff0000' ], $payload['colors']['gcid-primary-color'] );
	}

	public function test_extra_colors_have_full_divi_shape(): void {
		$color = $this->payload( [ 'color_accent' => '#00ff00' ] )['colors']['gcid-dbuddy-accent'];

		$this->assertSame( 'gcid-dbuddy-accent', $color['id'] );
		$this->assertSame( '#00ff00', $color['color'] );
		$this->assertSame( 'active', $color['status'] );
		$this->assertSame( self::NOW, $color['lastUpdated'] );
		$this->assertSame( [], $color['usedInPosts'] );
		$this->assertSame( Schema::fields()['color_accent']['label'], $color['label'], 'Divi label must match the settings page label.' );
		$this->assertIsString( $color['order'] );
	}

	public function test_fonts_target_divi_theme_options(): void {
		$payload = $this->payload( [ 'font_heading' => 'Roboto', 'font_body' => 'Lato' ] );
		$this->assertSame( [ 'heading_font' => 'Roboto', 'body_font' => 'Lato' ], $payload['fonts'] );
	}

	public function test_variables_are_in_divi_import_format(): void {
		$variables = array_column( $this->payload()['variables'], null, 'id' );

		foreach ( $variables as $variable ) {
			$this->assertSame( [ 'type', 'id', 'label', 'value', 'status' ], array_keys( $variable ) );
			$this->assertSame( 'numbers', $variable['type'] );
			$this->assertSame( 'active', $variable['status'] );
			$this->assertNotSame( '', $variable['value'], "{$variable['id']} has an empty value (Divi rejects those)." );
		}

		$this->assertStringStartsWith( 'clamp(', $variables['gvid-dbuddy-size-h1']['value'] );
		$this->assertSame( '700', $variables['gvid-dbuddy-heading-weight']['value'] );
		$this->assertSame( '1.2', $variables['gvid-dbuddy-heading-line-height']['value'] );
		$this->assertSame( '1200px', $variables['gvid-dbuddy-content-width']['value'] );
	}

	public function test_divi_labels_match_settings_page_labels(): void {
		$fields  = Schema::fields();
		$payload = $this->payload();
		$labels  = array_column( $payload['variables'], 'label', 'id' );
		foreach ( $payload['colors'] as $id => $color ) {
			if ( isset( $color['label'] ) ) {
				$labels[ $id ] = $color['label'];
			}
		}

		foreach ( $fields as $field ) {
			$id = $field['divi']['id'] ?? null;
			if ( isset( $labels[ $id ] ) ) {
				$this->assertSame( $field['label'], $labels[ $id ], "Divi label for {$id} differs from the settings page." );
			}
		}
		$this->assertCount( 28, $labels ); // 24 variables + 4 extra colors.
	}

	public function test_equal_fluid_sizes_become_plain_rem(): void {
		$variables = array_column( $this->payload( [ 'space_m' => [ 'desktop' => 24, 'mobile' => 24 ] ] )['variables'], null, 'id' );
		$this->assertSame( '1.5rem', $variables['gvid-dbuddy-space-m']['value'] );
	}
}
