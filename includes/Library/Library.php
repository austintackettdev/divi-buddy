<?php
/**
 * Reads the section files in the plugin's /library folder. Pure PHP (no WordPress).
 *
 * File format (library/<id>.json), written by bin/library-add.php:
 *   { "id": "hero-split", "title": "Hero Split", "divi_version": "5.19.0", "content": "<!-- wp:divi/placeholder -->…" }
 *
 * @package DiviBuddy
 */

namespace DiviBuddy\Library;

defined( 'ABSPATH' ) || exit;

/**
 * Library folder reader.
 */
final class Library {

	public function __construct( private string $dir ) {}

	/**
	 * All valid sections, keyed by id and sorted by title.
	 *
	 * @param string[] $errors Receives a message for each file that couldn't be used.
	 *
	 * @return array<string, array{id: string, title: string, divi_version: string, content: string}>
	 */
	public function sections( array &$errors = [] ): array {
		$sections = [];
		$files    = glob( rtrim( $this->dir, '/\\' ) . '/*.json' ) ?: [];

		foreach ( $files as $file ) {
			$section = self::parse( (string) file_get_contents( $file ), $error );
			if ( null === $section ) {
				$errors[] = basename( $file ) . ': ' . $error;
				continue;
			}
			if ( basename( $file, '.json' ) !== $section['id'] ) {
				$errors[] = basename( $file ) . ': the file name must match the section id "' . $section['id'] . '".';
				continue;
			}
			$sections[ $section['id'] ] = $section;
		}

		uasort( $sections, static fn( $a, $b ) => strnatcasecmp( $a['title'], $b['title'] ) );
		return $sections;
	}

	/**
	 * Validate one file's JSON. Returns null (and sets $error) when unusable.
	 */
	public static function parse( string $json, ?string &$error = null ): ?array {
		$data = json_decode( $json, true );
		if ( ! is_array( $data ) ) {
			$error = 'not valid JSON.';
			return null;
		}
		$id      = $data['id'] ?? null;
		$title   = $data['title'] ?? null;
		$content = $data['content'] ?? null;

		if ( ! is_string( $id ) || ! preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $id ) ) {
			$error = 'missing or invalid "id".';
			return null;
		}
		if ( ! is_string( $title ) || '' === trim( $title ) ) {
			$error = 'missing "title".';
			return null;
		}
		if ( ! is_string( $content ) || ! str_contains( $content, '<!-- wp:divi/' ) ) {
			$error = 'missing Divi 5 "content".';
			return null;
		}

		return [
			'id'           => $id,
			'title'        => trim( $title ),
			'divi_version' => is_string( $data['divi_version'] ?? null ) ? $data['divi_version'] : '',
			'content'      => $content,
		];
	}

	/**
	 * JSON for a library file (stable formatting so git diffs stay readable).
	 */
	public static function encode( array $section ): string {
		$data = [
			'id'           => $section['id'],
			'title'        => $section['title'],
			'divi_version' => $section['divi_version'] ?? '',
			'content'      => $section['content'],
		];
		return json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n";
	}
}
