<?php
namespace DiviBuddy\Tests\Unit;

use DiviBuddy\Support\FluidSize;
use DiviBuddy\Tests\TestCase;

final class FluidSizeTest extends TestCase {

	public function test_builds_clamp_between_mobile_and_desktop(): void {
		// 36px at 360px viewport → 56px at 1280px viewport.
		$this->assertSame( 'clamp(2.25rem, 1.761rem + 2.174vw, 3.5rem)', FluidSize::clamp( 36, 56, 360, 1280 ) );
	}

	public function test_clamp_hits_exact_sizes_at_both_viewports(): void {
		$clamp = FluidSize::clamp( 36, 56, 360, 1280 );
		preg_match( '/, ([\d.]+)rem \+ ([\d.]+)vw,/', $clamp, $m );
		$at = static fn( int $vw ) => ( (float) $m[1] * 16 ) + ( (float) $m[2] / 100 * $vw );

		$this->assertEqualsWithDelta( 36, $at( 360 ), 0.05 );
		$this->assertEqualsWithDelta( 56, $at( 1280 ), 0.05 );
	}

	public function test_equal_sizes_return_plain_rem(): void {
		$this->assertSame( '1rem', FluidSize::clamp( 16, 16, 360, 1280 ) );
	}

	public function test_mobile_larger_than_desktop_uses_negative_slope(): void {
		// 36px at 360px → 16px at 1280px: intercept = 36 + (20/920 × 360) = 43.826px = 2.739rem.
		$this->assertSame( 'clamp(1rem, 2.739rem - 2.174vw, 2.25rem)', FluidSize::clamp( 36, 16, 360, 1280 ) );
	}

	public function test_zero_size(): void {
		$this->assertSame( '0rem', FluidSize::clamp( 0, 0, 360, 1280 ) );
	}

	public function test_number_formatting(): void {
		$this->assertSame( '1.5', FluidSize::num( 1.5 ) );
		$this->assertSame( '2', FluidSize::num( 2.0 ) );
		$this->assertSame( '0.333', FluidSize::num( 1 / 3 ) );
		$this->assertSame( '0', FluidSize::num( -0.0001 ) );
	}
}
