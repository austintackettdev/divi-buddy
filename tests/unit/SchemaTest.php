<?php
namespace DiviBuddy\Tests\Unit;

use DiviBuddy\Settings\Sanitizer;
use DiviBuddy\Settings\Schema;
use DiviBuddy\Tests\TestCase;

final class SchemaTest extends TestCase {

	public function test_every_field_has_required_keys(): void {
		foreach ( Schema::fields() as $key => $field ) {
			foreach ( [ 'type', 'label', 'help', 'default', 'css', 'divi', 'group' ] as $required ) {
				$this->assertArrayHasKey( $required, $field, "Field {$key} is missing '{$required}'." );
			}
			$this->assertContains( $field['type'], [ 'color', 'font', 'select', 'number', 'fluid' ], "Field {$key} has an unknown type." );
		}
	}

	public function test_defaults_pass_the_sanitizer_unchanged(): void {
		$sanitizer = new Sanitizer( Schema::fields(), null );
		foreach ( Schema::fields() as $key => $field ) {
			$this->assertSame( $field['default'], $sanitizer->sanitize_field( $field, $field['default'] ), "Default for {$key} is not valid." );
		}
	}

	public function test_divi_ids_are_unique_and_well_formed(): void {
		$ids = [];
		foreach ( Schema::fields() as $key => $field ) {
			$divi = $field['divi'];
			$id   = $divi['id'] ?? $divi['option'];

			$this->assertNotContains( $id, $ids, "Duplicate Divi target {$id}." );
			$ids[] = $id;

			match ( $divi['kind'] ) {
				'customizer_color' => $this->assertContains( $id, [ 'gcid-primary-color', 'gcid-secondary-color', 'gcid-heading-color', 'gcid-body-color', 'gcid-link-color' ] ),
				'color'            => $this->assertMatchesRegularExpression( '/^gcid-dbuddy-[a-z0-9-]+$/', $id ),
				'customizer_font'  => $this->assertContains( $id, [ 'heading_font', 'body_font' ] ),
				'variable'         => $this->assertMatchesRegularExpression( '/^gvid-dbuddy-[a-z0-9-]+$/', $id ),
			};
		}
	}

	public function test_css_properties_are_unique_and_valid(): void {
		$props = array_column( Schema::fields(), 'css' );
		$this->assertSame( count( $props ), count( array_unique( $props ) ) );
		foreach ( $props as $prop ) {
			$this->assertMatchesRegularExpression( '/^--dbuddy-[a-z0-9-]+$/', $prop );
		}
	}

	public function test_builtin_color_labels_match_divi(): void {
		$fields = Schema::fields();
		$this->assertSame( 'Primary Color', $fields['color_primary']['label'] );
		$this->assertSame( 'Secondary Color', $fields['color_secondary']['label'] );
		$this->assertSame( 'Heading Text Color', $fields['color_heading']['label'] );
		$this->assertSame( 'Body Text Color', $fields['color_body']['label'] );
		$this->assertSame( 'Link Color', $fields['color_link']['label'] );
	}

	public function test_every_group_has_fields(): void {
		foreach ( Schema::groups() as $key => $group ) {
			$this->assertNotEmpty( $group['fields'], "Group {$key} has no fields." );
		}
	}
}
