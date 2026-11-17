<?php
namespace DiviBuddy\Tests\Unit;

use DiviBuddy\Frontend\CssBuilder;
use DiviBuddy\Settings\Schema;
use DiviBuddy\Tests\TestCase;

final class CssBuilderTest extends TestCase {

	public function test_prints_custom_properties_on_root(): void {
		$css = CssBuilder::build( Schema::fields(), array_merge( Schema::defaults(), [ 'color_primary' => '#ff0000', 'font_heading' => 'Roboto' ] ) );

		$this->assertStringStartsWith( ':root{', $css );
		$this->assertStringContainsString( '--dbuddy-color-primary:#ff0000;', $css );
		$this->assertStringContainsString( "--dbuddy-font-heading:'Roboto', sans-serif;", $css );
		$this->assertStringContainsString( '--dbuddy-size-h1:clamp(', $css );
		$this->assertStringContainsString( '--dbuddy-radius-button:6px;', $css );
	}

	public function test_applies_text_settings_site_wide(): void {
		$css = CssBuilder::build( Schema::fields(), Schema::defaults() );

		$this->assertStringContainsString( 'body h1{font-size:var(--dbuddy-size-h1);}', $css );
		$this->assertStringContainsString( 'body,body p{font-size:var(--dbuddy-size-body);line-height:var(--dbuddy-body-line-height);font-weight:var(--dbuddy-body-weight);}', $css );
		$this->assertStringContainsString( 'font-weight:var(--dbuddy-heading-weight)', $css );
	}

	public function test_site_rules_can_be_turned_off(): void {
		$css = CssBuilder::build( Schema::fields(), Schema::defaults(), false );

		$this->assertStringNotContainsString( 'body h1', $css );
		$this->assertMatchesRegularExpression( '/^:root\{[^}]*\}$/', $css );
	}

	public function test_unsafe_values_are_never_printed(): void {
		// Bypass the sanitizer on purpose to prove the builder has its own guard.
		$css = CssBuilder::build( Schema::fields(), array_merge( Schema::defaults(), [ 'color_primary' => 'red;}</style><script>' ] ) );

		$this->assertStringNotContainsString( '<script>', $css );
		$this->assertStringNotContainsString( '--dbuddy-color-primary', $css );
	}
}
