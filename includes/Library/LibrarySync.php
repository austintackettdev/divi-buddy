<?php
/**
 * Creates and updates Divi Library items from the plugin's /library sections.
 *
 * - Runs automatically on the next admin page load whenever the library files change (activation,
 *   plugin update, or a new file on the dev site), and from the "Update section library" button.
 * - Only touches Divi Library items it created (marked with the `_dbuddy_library_id` meta).
 * - If someone on the site edited a Divi Buddy library item, it is left alone and reported as
 *   "changed on this site"; an admin can restore the original. Pages that already use a section
 *   have their own copy and are never affected.
 * - Sections removed from the plugin are moved to the trash (unless edited on the site).
 *
 * @package DiviBuddy
 */

namespace DiviBuddy\Library;

use DiviBuddy\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Library → Divi Library sync.
 */
final class LibrarySync {

	public const POST_TYPE          = 'et_pb_layout';
	public const CATEGORY           = 'Divi Buddy';
	public const STATUS_OPTION      = 'dbuddy_library_status';
	public const FINGERPRINT_OPTION = 'dbuddy_library_fingerprint';

	/** Library section id. */
	public const META_ID = '_dbuddy_library_id';
	/** Hash of the library version last written (detects new versions in the plugin). */
	public const META_SOURCE_HASH = '_dbuddy_source_hash';
	/** Hash of the item as written (detects edits made on the site). */
	public const META_LOCAL_HASH = '_dbuddy_local_hash';
	/** Set when Divi Buddy itself trashed the item (section removed from the plugin). */
	public const META_REMOVED = '_dbuddy_removed';

	private Library $library;

	public function __construct( ?Library $library = null ) {
		$this->library = $library ?? new Library( DBUDDY_PATH . 'library' );
	}

	public function register(): void {
		add_action( 'admin_init', [ $this, 'maybe_sync' ], 20 );
	}

	/**
	 * Sync when the library files changed since the last sync.
	 */
	public function maybe_sync(): void {
		if ( wp_doing_ajax() || ! current_user_can( Plugin::CAPABILITY ) ) {
			return;
		}
		if ( get_option( self::FINGERPRINT_OPTION ) === $this->fingerprint() ) {
			return;
		}
		$this->run();
	}

	/**
	 * Cheap fingerprint of the library folder (file names, sizes, modification times) plus the
	 * placeholder URL, which is baked into the content on each site.
	 */
	public function fingerprint(): string {
		$parts = [ self::placeholder_url(), DBUDDY_VERSION ];
		foreach ( glob( DBUDDY_PATH . 'library/*.json' ) ?: [] as $file ) {
			$parts[] = basename( $file ) . ':' . filesize( $file ) . ':' . filemtime( $file );
		}
		return md5( implode( '|', $parts ) );
	}

	/**
	 * @param string[] $force_ids Section ids to rewrite even if they were edited on this site (restore).
	 *
	 * @return array{time: int, created: int, updated: int, kept: int, removed: int, errors: string[]}
	 */
	public function run( array $force_ids = [] ): array {
		$status = [
			'time'    => time(),
			'created' => 0,
			'updated' => 0,
			'kept'    => 0,
			'removed' => 0,
			'errors'  => [],
		];

		if ( ! post_type_exists( self::POST_TYPE ) ) {
			$status['errors'][] = __( 'The Divi Library is not available, so the section library could not be updated.', 'divi-buddy' );
			return $this->record( $status, false );
		}

		$errors   = [];
		$sections = $this->library->sections( $errors );
		foreach ( $errors as $error ) {
			/* translators: %s: file name and problem. */
			$status['errors'][] = sprintf( __( 'Skipped a library file: %s', 'divi-buddy' ), $error );
		}

		$existing = $this->existing_items();

		foreach ( $sections as $id => $section ) {
			$content     = self::resolve( $section['content'] );
			$source_hash = md5( $section['title'] . "\n" . $content );
			$post        = $existing[ $id ] ?? null;

			try {
				if ( null === $post ) {
					$this->write( null, $id, $section['title'], $content, $source_hash );
					++$status['created'];
					continue;
				}

				// Trashed by Divi Buddy because the section was removed from the plugin, and now it's back.
				$returning = 'trash' === $post->post_status && get_post_meta( $post->ID, self::META_REMOVED, true );

				if ( 'trash' === $post->post_status && ! $returning && ! in_array( $id, $force_ids, true ) ) {
					continue; // Someone on this site deleted it: respect that.
				}

				$stored_source = (string) get_post_meta( $post->ID, self::META_SOURCE_HASH, true );
				$modified      = self::is_modified( $post );
				$forced        = $returning || in_array( $id, $force_ids, true );

				if ( $stored_source === $source_hash && ! ( $forced && ( $modified || 'trash' === $post->post_status ) ) ) {
					continue; // Already up to date.
				}
				if ( $modified && ! $forced ) {
					++$status['kept'];
					continue;
				}

				$this->write( $post->ID, $id, $section['title'], $content, $source_hash );
				++$status['updated'];
			} catch ( \Throwable $e ) {
				/* translators: 1: section title, 2: error message. */
				$status['errors'][] = sprintf( __( 'Could not save "%1$s": %2$s', 'divi-buddy' ), $section['title'], $e->getMessage() );
			}
		}

		// Sections removed from the plugin.
		foreach ( $existing as $id => $post ) {
			if ( isset( $sections[ $id ] ) || 'trash' === $post->post_status || self::is_modified( $post ) ) {
				continue;
			}
			update_post_meta( $post->ID, self::META_REMOVED, 1 );
			if ( wp_trash_post( $post->ID ) ) {
				++$status['removed'];
			}
		}

		return $this->record( $status, true );
	}

	/**
	 * Restore one section to the plugin's version (discarding edits made on this site).
	 */
	public function restore( string $id ): array {
		return $this->run( [ $id ] );
	}

	/**
	 * Library sections with their state on this site, for the admin screen.
	 *
	 * @return array<string, array{title: string, state: string, post_id: int}>
	 *   state: up_to_date | update_pending | modified | deleted | not_added
	 */
	public function items(): array {
		$items    = [];
		$existing = $this->existing_items();

		foreach ( $this->library->sections() as $id => $section ) {
			$post  = $existing[ $id ] ?? null;
			$state = 'not_added';

			if ( $post ) {
				$source = md5( $section['title'] . "\n" . self::resolve( $section['content'] ) );
				if ( 'trash' === $post->post_status ) {
					$state = 'deleted';
				} elseif ( self::is_modified( $post ) ) {
					$state = 'modified';
				} elseif ( get_post_meta( $post->ID, self::META_SOURCE_HASH, true ) !== $source ) {
					$state = 'update_pending';
				} else {
					$state = 'up_to_date';
				}
			}

			$items[ $id ] = [
				'title'   => $section['title'],
				'state'   => $state,
				'post_id' => $post ? (int) $post->ID : 0,
			];
		}
		return $items;
	}

	/**
	 * @return array{time: int, created: int, updated: int, kept: int, removed: int, errors: string[]}|null
	 */
	public static function last_status(): ?array {
		$status = get_option( self::STATUS_OPTION );
		return is_array( $status ) ? $status : null;
	}

	/* ------------------------------------------------------------------ */

	/**
	 * Divi Buddy's existing library items, keyed by section id (any status, including trash).
	 *
	 * @return array<string, \WP_Post>
	 */
	private function existing_items(): array {
		$posts = get_posts(
			[
				'post_type'        => self::POST_TYPE,
				'post_status'      => [ 'any', 'trash' ],
				'posts_per_page'   => -1,
				'meta_key'         => self::META_ID, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'suppress_filters' => true,
			]
		);
		$items = [];
		foreach ( $posts as $post ) {
			$id = (string) get_post_meta( $post->ID, self::META_ID, true );
			if ( '' !== $id && ! isset( $items[ $id ] ) ) {
				$items[ $id ] = $post;
			}
		}
		return $items;
	}

	private function write( ?int $post_id, string $id, string $title, string $content, string $source_hash ): void {
		$postarr = [
			'post_type'    => self::POST_TYPE,
			'post_status'  => 'publish',
			'post_title'   => $title,
			'post_content' => $content,
		];
		if ( $post_id ) {
			$postarr['ID'] = $post_id;
		}

		// The content comes from the plugin's own files, so don't let kses strip Divi's block markup
		// for users without `unfiltered_html` (e.g. administrators on multisite).
		$kses = false !== has_filter( 'content_save_pre', 'wp_filter_post_kses' );
		if ( $kses ) {
			kses_remove_filters();
		}
		$result = $post_id ? wp_update_post( wp_slash( $postarr ), true ) : wp_insert_post( wp_slash( $postarr ), true );
		if ( $kses ) {
			kses_init_filters();
		}

		if ( is_wp_error( $result ) ) {
			throw new \RuntimeException( $result->get_error_message() );
		}
		$new_id = (int) $result;

		update_post_meta( $new_id, self::META_ID, $id );
		delete_post_meta( $new_id, self::META_REMOVED );
		update_post_meta( $new_id, '_et_pb_built_for_post_type', 'page' );
		update_post_meta( $new_id, '_et_pb_template_type', 'section' );
		update_post_meta( $new_id, '_et_pb_use_divi_5', 'on' );
		update_post_meta( $new_id, self::META_SOURCE_HASH, $source_hash );

		wp_set_object_terms( $new_id, 'section', 'layout_type' );
		wp_set_object_terms( $new_id, 'non_global', 'scope' );
		wp_set_object_terms( $new_id, 'regular', 'module_width' );
		wp_set_object_terms( $new_id, self::CATEGORY, 'layout_category' );

		// Hash what was actually stored, so later edits on the site can be detected reliably.
		clean_post_cache( $new_id );
		$saved = get_post( $new_id );
		update_post_meta( $new_id, self::META_LOCAL_HASH, self::local_hash( $saved ) );
	}

	private static function is_modified( \WP_Post $post ): bool {
		$stored = (string) get_post_meta( $post->ID, self::META_LOCAL_HASH, true );
		return '' !== $stored && self::local_hash( $post ) !== $stored;
	}

	private static function local_hash( ?\WP_Post $post ): string {
		return $post ? md5( $post->post_title . "\n" . $post->post_content ) : '';
	}

	/**
	 * Fill in site-specific values (the placeholder image URL).
	 */
	public static function resolve( string $content ): string {
		return str_replace( SectionConverter::PLACEHOLDER_TOKEN, self::placeholder_url(), $content );
	}

	public static function placeholder_url(): string {
		return DBUDDY_URL . 'assets/library/placeholder.svg';
	}

	private function record( array $status, bool $save_fingerprint ): array {
		update_option( self::STATUS_OPTION, $status, false );
		// Saved even when a file had errors (they're shown on the Divi Buddy page), so a broken file
		// doesn't trigger a new sync on every admin page load.
		if ( $save_fingerprint ) {
			update_option( self::FINGERPRINT_OPTION, $this->fingerprint(), false );
		}
		return $status;
	}
}
