<?php
/**
 * Builds the data sent to Divi from Divi Buddy settings. Pure logic so it can be unit tested.
 *
 * @package DiviBuddy
 */

namespace DiviBuddy\Divi;

use DiviBuddy\Support\ValueFormatter;

defined( 'ABSPATH' ) || exit;

/**
 * Settings → Divi payload.
 */
final class PayloadBuilder {

	/** Divi Buddy's variables are listed after the site's own variables in Divi's manager. */
	private const ORDER_START = 1000;

	/**
	 * @param array<string, array> $fields Schema fields.
	 * @param array<string, mixed> $values Sanitized setting values.
	 * @param string               $now    Timestamp in Divi's format (Y-m-d\TH:i:s.v\Z).
	 *
	 * @return array{
	 *   colors: array<string, array>,
	 *   fonts: array<string, string>,
	 *   variables: array<int, array{type:string, id:string, label:string, value:string, status:string}>
	 * }
	 */
	public static function build( array $fields, array $values, string $now ): array {
		$colors    = [];
		$fonts     = [];
		$variables = [];
		$order     = self::ORDER_START;

		foreach ( $fields as $key => $field ) {
			$divi  = $field['divi'] ?? null;
			$value = $values[ $key ] ?? $field['default'];

			if ( ! is_array( $divi ) ) {
				continue;
			}

			switch ( $divi['kind'] ) {
				case 'customizer_color':
					// Divi stores these 5 in its own theme options; set_global_colors() routes them there.
					$colors[ $divi['id'] ] = [ 'color' => (string) $value ];
					break;

				case 'color':
					$colors[ $divi['id'] ] = [
						'id'          => $divi['id'],
						'label'       => $field['label'],
						'color'       => (string) $value,
						'order'       => (string) $order++,
						'status'      => 'active',
						'folder'      => '',
						'lastUpdated' => $now,
						'usedInPosts' => [],
					];
					break;

				case 'customizer_font':
					$fonts[ $divi['option'] ] = (string) $value;
					break;

				case 'variable':
					$variables[] = [
						'type'   => $divi['type'],
						'id'     => $divi['id'],
						'label'  => $field['label'],
						'value'  => ValueFormatter::css( $field, $value ),
						'status' => 'active',
					];
					break;
			}
		}

		return [
			'colors'    => $colors,
			'fonts'     => $fonts,
			'variables' => $variables,
		];
	}
}
