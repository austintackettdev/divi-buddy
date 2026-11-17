<?php
/**
 * Adds or updates sections in the Divi Buddy library from Divi Library export files.
 *
 * Usage:
 *   composer library:add                     Converts every .json file in library-inbox/
 *   composer library:add -- path/to/file.json [more files or folders]
 *
 * Each section is checked for portability (see SectionConverter). Sections with errors are not
 * written. Valid sections are saved to library/<id>.json; an existing file with the same id is
 * replaced (that's how you release an updated version of a section).
 *
 * @package DiviBuddy
 */

define( 'ABSPATH', __DIR__ . '/' ); // The plugin classes refuse to load without it.

$root = dirname( __DIR__ );
require $root . '/includes/Library/BlockMarkup.php';
require $root . '/includes/Library/SectionConverter.php';
require $root . '/includes/Library/Library.php';

use DiviBuddy\Library\Library;
use DiviBuddy\Library\SectionConverter;

$inputs = array_slice( $argv, 1 );
if ( ! $inputs ) {
	$inputs = [ $root . '/library-inbox' ];
}

$files = [];
foreach ( $inputs as $input ) {
	if ( is_dir( $input ) ) {
		$files = array_merge( $files, glob( rtrim( $input, '/\\' ) . '/*.json' ) ?: [] );
	} elseif ( is_file( $input ) ) {
		$files[] = $input;
	} else {
		fwrite( STDERR, "Not found: {$input}\n" );
	}
}

if ( ! $files ) {
	echo "No export files found. Put Divi Library exports (.json) in library-inbox/ or pass their paths.\n";
	exit( 1 );
}

$library_dir = $root . '/library';
if ( ! is_dir( $library_dir ) ) {
	mkdir( $library_dir, 0755, true );
}

$converter  = new SectionConverter();
$had_errors = false;

foreach ( $files as $file ) {
	echo "\n" . basename( $file ) . "\n";

	$export = json_decode( (string) file_get_contents( $file ), true );
	$result = $converter->convert_export( $export );

	foreach ( $result['problems'] as $problem ) {
		$label = 'error' === $problem['level'] ? 'ERROR  ' : 'warning';
		$where = '' !== $problem['title'] ? "[{$problem['title']}] " : '';
		echo "  {$label} {$where}{$problem['message']}\n";
		$had_errors = $had_errors || 'error' === $problem['level'];
	}

	foreach ( $result['sections'] as $section ) {
		$path   = $library_dir . '/' . $section['id'] . '.json';
		$exists = is_file( $path );
		file_put_contents( $path, Library::encode( $section ) );
		printf( "  %s  \"%s\" → library/%s.json\n", $exists ? 'UPDATED' : 'ADDED  ', $section['title'], $section['id'] );
	}
}

echo $had_errors ? "\nSome sections were not added. Fix the errors above in Divi, export again and re-run.\n" : "\nDone.\n";
exit( $had_errors ? 1 : 0 );
