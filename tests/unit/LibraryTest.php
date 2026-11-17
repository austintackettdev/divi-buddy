<?php
namespace DiviBuddy\Tests\Unit;

use DiviBuddy\Library\Library;
use DiviBuddy\Tests\TestCase;

final class LibraryTest extends TestCase {

	private string $dir;

	protected function setUp(): void {
		parent::setUp();
		$this->dir = sys_get_temp_dir() . '/dbuddy-lib-' . uniqid();
		mkdir( $this->dir );
	}

	protected function tearDown(): void {
		foreach ( glob( $this->dir . '/*' ) ?: [] as $file ) {
			unlink( $file );
		}
		rmdir( $this->dir );
		parent::tearDown();
	}

	private function section( string $id, string $title ): array {
		return [ 'id' => $id, 'title' => $title, 'divi_version' => '5.19.0', 'content' => '<!-- wp:divi/placeholder --><!-- /wp:divi/placeholder -->' ];
	}

	public function test_loads_valid_files_sorted_by_title(): void {
		file_put_contents( $this->dir . '/zeta.json', Library::encode( $this->section( 'zeta', 'Zeta' ) ) );
		file_put_contents( $this->dir . '/alpha.json', Library::encode( $this->section( 'alpha', 'Alpha' ) ) );

		$sections = ( new Library( $this->dir ) )->sections();

		$this->assertSame( [ 'alpha', 'zeta' ], array_keys( $sections ) );
	}

	public function test_reports_bad_files(): void {
		file_put_contents( $this->dir . '/broken.json', '{nope' );
		file_put_contents( $this->dir . '/wrong-name.json', Library::encode( $this->section( 'other', 'Other' ) ) );
		file_put_contents( $this->dir . '/no-content.json', json_encode( [ 'id' => 'no-content', 'title' => 'X', 'content' => '<p>hi</p>' ] ) );

		$errors   = [];
		$sections = ( new Library( $this->dir ) )->sections( $errors );

		$this->assertSame( [], $sections );
		$this->assertCount( 3, $errors );
	}

	public function test_missing_folder_means_empty_library(): void {
		$this->assertSame( [], ( new Library( $this->dir . '/missing' ) )->sections() );
	}

	public function test_rejects_unsafe_ids(): void {
		$this->assertNull( Library::parse( json_encode( [ 'id' => '../evil', 'title' => 'X', 'content' => '<!-- wp:divi/section -->' ] ) ) );
		$this->assertNull( Library::parse( json_encode( [ 'id' => 'Hero', 'title' => 'X', 'content' => '<!-- wp:divi/section -->' ] ) ) );
	}
}
