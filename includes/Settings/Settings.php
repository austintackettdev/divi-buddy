<?php
/**
 * Reads and writes the `dbuddy_settings` option.
 *
 * @package DiviBuddy
 */

namespace DiviBuddy\Settings;

use DiviBuddy\Divi\Divi;

defined( 'ABSPATH' ) || exit;

/**
 * Settings store.
 */
final class Settings {

	public const OPTION = 'dbuddy_settings';

	/**
	 * Saved values merged over defaults.
	 *
	 * Before the first save, Divi's own built-in colors and fonts are used as the defaults, so
	 * installing Divi Buddy never changes how an existing site looks.
	 *
	 * @return array<string, mixed>
	 */
	public function values(): array {
		$stored = get_option( self::OPTION );
		$values = is_array( $stored ) && is_array( $stored['values'] ?? null ) ? $stored['values'] : [];

		return array_merge( $this->defaults(), array_intersect_key( $values, Schema::fields() ) );
	}

	public function is_saved(): bool {
		return is_array( get_option( self::OPTION ) );
	}

	/**
	 * Schema defaults, with Divi's current built-in colors and fonts if the site has never saved.
	 */
	public function defaults(): array {
		$defaults = Schema::defaults();
		if ( $this->is_saved() ) {
			return $defaults;
		}
		return array_merge( $defaults, Divi::current_builtin_values() );
	}

	/**
	 * Save a full set of (already sanitized) values.
	 */
	public function save( array $values ): void {
		$sanitizer = new Sanitizer( Schema::fields(), null );
		$clean     = $sanitizer->sanitize( $values, Schema::defaults() )['values'];

		$this->trusted_write = true;
		update_option( self::OPTION, self::wrap( $clean ), false );
		$this->trusted_write = false;
	}

	/** True while save() is writing, so the Settings API sanitize callback passes the data through. */
	private bool $trusted_write = false;

	/**
	 * Reset everything to Divi Buddy's defaults.
	 */
	public function reset(): void {
		$this->save( Schema::defaults() );
	}

	public static function wrap( array $values ): array {
		return [
			'schema_version' => Schema::VERSION,
			'values'         => $values,
		];
	}

	/**
	 * Settings API sanitize callback for the whole option.
	 *
	 * @param mixed $input Raw submitted option value.
	 */
	public function sanitize_option( mixed $input ): array {
		// Written by save()/reset(), which already sanitized the values.
		if ( $this->trusted_write && is_array( $input ) ) {
			return $input;
		}

		$raw       = is_array( $input ) && is_array( $input['values'] ?? null ) ? $input['values'] : [];
		$sanitizer = new Sanitizer( Schema::fields(), Divi::font_names() );
		$result    = $sanitizer->sanitize( $raw, $this->values() );

		foreach ( $result['errors'] as $error ) {
			add_settings_error( self::OPTION, 'dbuddy_' . $error['field'], $error['message'], 'warning' );
		}

		return self::wrap( $result['values'] );
	}
}
