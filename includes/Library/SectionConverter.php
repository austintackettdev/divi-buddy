<?php
/**
 * Turns a Divi Library export (.json) into portable Divi Buddy library sections.
 *
 * Portability rules (see docs/divi5-notes.md → "Section portability rules"):
 *  - Images: every image URL is swapped for the placeholder token; media IDs next to them are dropped.
 *  - Presets: a section that uses a module/group preset is rejected (preset IDs only exist on one site).
 *  - Colors/variables: only Divi Buddy's (gcid-dbuddy-*, gvid-dbuddy-*) and Divi's built-in ones are
 *    allowed. Anything else only exists on the site it was built on, so the section is rejected.
 *  - Other absolute links are allowed but reported as warnings.
 *
 * Pure PHP (no WordPress) so it runs from bin/library-add.php and in unit tests.
 *
 * @package DiviBuddy
 */

namespace DiviBuddy\Library;

defined( 'ABSPATH' ) || exit;

/**
 * Divi export → library sections.
 */
final class SectionConverter {

	public const PLACEHOLDER_TOKEN = '{{dbuddy:placeholder-image}}';

	private const IMAGE_URL = '#(?:https?:)?//[^\s"\'<>()]+?\.(?:jpe?g|png|gif|webp|avif|svg)(?:\?[^\s"\'<>()]*)?(?=$|[\s"\'<>()])#i';

	private const ALLOWED_GLOBAL_IDS = [
		'gcid-primary-color',
		'gcid-secondary-color',
		'gcid-heading-color',
		'gcid-body-color',
		'gcid-link-color',
		'--et_global_heading_font',
		'--et_global_body_font',
	];

	/**
	 * Convert a decoded Divi export file.
	 *
	 * @param mixed $export json_decode()d export (associative arrays).
	 *
	 * @return array{sections: array<int, array>, problems: array<int, array{title: string, level: string, message: string}>}
	 */
	public function convert_export( mixed $export ): array {
		$sections = [];
		$problems = [];

		if ( ! is_array( $export ) || ( $export['context'] ?? '' ) !== 'et_builder_layouts' || ! is_array( $export['data'] ?? null ) ) {
			$problems[] = self::problem( '', 'error', 'This is not a Divi Library export file. In Divi, export the section from Divi → Divi Library.' );
			return compact( 'sections', 'problems' );
		}

		foreach ( $export['data'] as $post ) {
			$title = trim( (string) ( $post['post_title'] ?? '' ) );
			$type  = self::layout_type( $post );

			if ( 'section' !== $type ) {
				$problems[] = self::problem( $title, 'error', sprintf( 'This is a "%s", not a section. Only sections can go in the library.', $type ?: 'unknown item' ) );
				continue;
			}
			if ( '' === $title ) {
				$problems[] = self::problem( $title, 'error', 'The section has no name. Give it a name in the Divi Library and export it again.' );
				continue;
			}

			$result = $this->convert_content( (string) ( $post['post_content'] ?? '' ) );
			foreach ( $result['errors'] as $message ) {
				$problems[] = self::problem( $title, 'error', $message );
			}
			foreach ( $result['warnings'] as $message ) {
				$problems[] = self::problem( $title, 'warning', $message );
			}
			if ( $result['errors'] ) {
				continue;
			}

			$sections[] = [
				'id'           => self::slugify( $title ),
				'title'        => $title,
				'divi_version' => self::builder_version( $result['content'] ),
				'content'      => $result['content'],
			];
		}

		return compact( 'sections', 'problems' );
	}

	/**
	 * Check and transform one section's block markup.
	 *
	 * @return array{content: string, errors: string[], warnings: string[]}
	 */
	public function convert_content( string $content ): array {
		$content  = str_replace( "\r\n", "\n", $content ); // Exports made on Windows can carry CRLF.
		$errors   = [];
		$warnings = [];
		$images   = 0;

		if ( ! str_contains( $content, '<!-- wp:divi/' ) ) {
			return [
				'content'  => $content,
				'errors'   => [ 'The section has no Divi 5 content. Make sure it was built with Divi 5.' ],
				'warnings' => [],
			];
		}

		$parse_errors = [];
		$content      = BlockMarkup::map_attributes(
			$content,
			function ( string $block, object $attrs ) use ( &$errors, &$warnings, &$images ): ?object {
				$changed = false;
				$this->walk( $attrs, $block, $changed, $errors, $warnings, $images );
				return $changed ? $attrs : null;
			},
			$parse_errors
		);
		$errors = array_merge( $parse_errors, $errors );

		if ( $images ) {
			$warnings[] = sprintf( '%d image(s) were swapped for the placeholder image. Clients replace them after inserting the section.', $images );
		}

		return [
			'content'  => $content,
			'errors'   => array_values( array_unique( $errors ) ),
			'warnings' => array_values( array_unique( $warnings ) ),
		];
	}

	/**
	 * Recursively inspect and fix attribute values in place.
	 */
	private function walk( object|array &$node, string $block, bool &$changed, array &$errors, array &$warnings, int &$images ): void {
		$replaced_keys = [];

		foreach ( $node as $key => &$value ) {
			if ( in_array( $key, [ 'modulePreset', 'groupPreset' ], true ) && ! self::is_empty( $value ) ) {
				$errors[] = sprintf( 'The %s element uses a preset. Presets only exist on the site they were made on: remove the preset from that element in Divi and export again.', ucfirst( self::short( $block ) ) );
				continue;
			}

			if ( is_object( $value ) || is_array( $value ) ) {
				$this->walk( $value, $block, $changed, $errors, $warnings, $images );
				continue;
			}

			if ( ! is_string( $value ) || '' === $value ) {
				continue;
			}

			// Global colors / variables.
			foreach ( self::global_references( $value ) as $id ) {
				if ( ! self::is_allowed_global( $id ) ) {
					$errors[] = sprintf( 'Uses the color or variable "%s", which only exists on the site it was built on. Use a Divi Buddy color/variable or one of Divi\'s built-in colors instead.', $id );
				}
			}

			// Images → placeholder.
			$count = 0;
			$new   = preg_replace( self::IMAGE_URL, self::PLACEHOLDER_TOKEN, $value, -1, $count );
			if ( $count ) {
				$value    = (string) $new;
				$changed  = true;
				$images  += $count;
				if ( in_array( $key, [ 'src', 'url' ], true ) ) {
					$replaced_keys[] = $key;
				}
			}

			// Other absolute links (allowed, but probably need changing on each site).
			if ( preg_match_all( '#https?://[^\s"\'<>()]+#i', $value, $links ) ) {
				foreach ( $links[0] as $link ) {
					$warnings[] = sprintf( 'Links to %s. Clients may need to change this link after inserting the section.', $link );
				}
			}
		}
		unset( $value );

		// Media library IDs belong to the source site: drop them next to swapped images.
		if ( $replaced_keys ) {
			foreach ( [ 'id', 'attachmentId' ] as $id_key ) {
				if ( is_object( $node ) && property_exists( $node, $id_key ) ) {
					unset( $node->$id_key );
				} elseif ( is_array( $node ) && array_key_exists( $id_key, $node ) ) {
					unset( $node[ $id_key ] );
				}
			}
		}
	}

	/**
	 * Color/variable IDs referenced by a value: $variable({...})$ tokens and var(--gcid-…/--gvid-…).
	 *
	 * @return string[]
	 */
	public static function global_references( string $value ): array {
		$ids = [];
		if ( preg_match_all( '/\$variable\((.*?)\)\$/s', $value, $m ) ) {
			foreach ( $m[1] as $json ) {
				$payload = json_decode( $json, true );
				$name    = is_array( $payload ) ? ( $payload['value']['name'] ?? null ) : null;
				if ( is_string( $name ) && '' !== $name ) {
					$ids[] = $name;
				}
			}
		}
		if ( preg_match_all( '/var\(\s*--((?:gcid|gvid)-[a-zA-Z0-9_-]+)/', $value, $m ) ) {
			array_push( $ids, ...$m[1] );
		}
		return array_values( array_unique( $ids ) );
	}

	public static function is_allowed_global( string $id ): bool {
		return in_array( $id, self::ALLOWED_GLOBAL_IDS, true )
			|| (bool) preg_match( '/^(gcid|gvid)-dbuddy-[a-z0-9-]+$/', $id );
	}

	/**
	 * Library ID from a title: "Hero — Split (Dark)" → "hero-split-dark".
	 */
	public static function slugify( string $title ): string {
		$slug = strtolower( (string) preg_replace( '/[^A-Za-z0-9]+/', '-', $title ) );
		$slug = trim( $slug, '-' );
		return '' !== $slug ? substr( $slug, 0, 80 ) : 'section';
	}

	private static function layout_type( array $post ): string {
		foreach ( (array) ( $post['terms'] ?? [] ) as $term ) {
			if ( 'layout_type' === ( $term['taxonomy'] ?? '' ) ) {
				return (string) ( $term['slug'] ?? '' );
			}
		}
		$meta = $post['post_meta']['_et_pb_template_type'] ?? null;
		return is_array( $meta ) ? (string) ( $meta[0] ?? '' ) : (string) $meta;
	}

	private static function builder_version( string $content ): string {
		return preg_match( '/"builderVersion":"([^"]+)"/', $content, $m ) ? $m[1] : '';
	}

	private static function is_empty( mixed $value ): bool {
		return null === $value || '' === $value || [] === $value || ( is_object( $value ) && [] === get_object_vars( $value ) );
	}

	private static function short( string $block ): string {
		return str_replace( 'divi/', '', $block );
	}

	private static function problem( string $title, string $level, string $message ): array {
		return compact( 'title', 'level', 'message' );
	}
}
