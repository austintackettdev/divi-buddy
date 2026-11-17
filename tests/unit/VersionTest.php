<?php
namespace DiviBuddy\Tests\Unit;

use DiviBuddy\Tests\TestCase;

/**
 * The updater compares the plugin header version with GitHub release tags, so both version
 * strings in the main file must always agree.
 */
final class VersionTest extends TestCase {

	public function test_header_version_matches_constant(): void {
		$source = (string) file_get_contents( dirname( __DIR__, 2 ) . '/divi-buddy.php' );

		$this->assertMatchesRegularExpression( '/^\s*\*\s*Version:\s*(\S+)/m', $source );
		preg_match( '/^\s*\*\s*Version:\s*(\S+)/m', $source, $header );
		preg_match( "/define\(\s*'DBUDDY_VERSION',\s*'([^']+)'\s*\)/", $source, $constant );

		$this->assertSame( $header[1], $constant[1] ?? null, 'Plugin header Version and DBUDDY_VERSION differ.' );
		$this->assertMatchesRegularExpression( '/^\d+\.\d+\.\d+$/', $header[1] );
	}

	public function test_header_has_update_uri(): void {
		$source = (string) file_get_contents( dirname( __DIR__, 2 ) . '/divi-buddy.php' );
		$this->assertMatchesRegularExpression( '#^\s*\*\s*Update URI:\s*https://github\.com/austintackettdev/divi-buddy\s*$#m', $source );
	}
}
