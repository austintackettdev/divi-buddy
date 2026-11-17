<?php
/**
 * Settings schema: the single source of truth for every Divi Buddy setting.
 *
 * The admin form, sanitizing, defaults, the Divi sync and the CSS output are all generated
 * from this list. To add a new global, add an entry here.
 *
 * Field keys:
 *  - type     color | font | select | number | fluid
 *  - label    Plain-language label shown to clients.
 *  - help     One-line help text shown under the field.
 *  - default  Default value. For `fluid`: [ 'desktop' => px, 'mobile' => px ].
 *  - css      CSS custom property printed on :root (fallback output).
 *  - divi     Where the value goes in Divi:
 *               [ 'kind' => 'customizer_color', 'id' => 'gcid-…' ]   Divi's 5 built-in colors
 *               [ 'kind' => 'color', 'id' => 'gcid-dbuddy-…' ]        Extra global colors
 *               [ 'kind' => 'customizer_font', 'option' => '…' ]      Divi's 2 built-in fonts
 *               [ 'kind' => 'variable', 'type' => 'numbers', 'id' => 'gvid-dbuddy-…' ]
 *
 * The `label` is also the name shown in the Divi Builder (for `color` and `variable` kinds), so the
 * settings page and the builder always use the same names.
 *
 *  - unit, min, max, step   For number/fluid fields.
 *  - options  For select fields: value => label.
 *  - apply    Optional element this setting styles site-wide (see CssBuilder).
 *
 * @package DiviBuddy
 */

namespace DiviBuddy\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Settings schema definition.
 */
final class Schema {

	/** Bump when stored data needs a migration. */
	public const VERSION = 1;

	/** Viewport range (px) over which fluid sizes scale from their mobile to desktop value. */
	public const FLUID_MIN_VIEWPORT = 360;
	public const FLUID_MAX_VIEWPORT = 1280;

	/**
	 * Groups of fields, in display order (each group is one tab).
	 *
	 * @return array<string, array{label:string, description:string, fields:array<string, array>}>
	 */
	public static function groups(): array {
		return [
			'colors'     => [
				'label'       => __( 'Colors', 'divi-buddy' ),
				'description' => __( 'Your brand colors. They appear in the Divi Builder color picker, so you can use them anywhere on the site.', 'divi-buddy' ),
				'fields'      => self::color_fields(),
			],
			'typography' => [
				'label'       => __( 'Fonts & Text', 'divi-buddy' ),
				'description' => __( 'Fonts and text sizes for the whole site. Sizes shrink smoothly between the desktop and mobile values you set.', 'divi-buddy' ),
				'fields'      => self::typography_fields(),
			],
			'spacing'    => [
				'label'       => __( 'Spacing', 'divi-buddy' ),
				'description' => __( 'How much space goes around and between things. Smaller screens use the mobile value.', 'divi-buddy' ),
				'fields'      => self::spacing_fields(),
			],
			'shapes'     => [
				'label'       => __( 'Corners & Width', 'divi-buddy' ),
				'description' => __( 'How rounded corners are, and how wide page content can get.', 'divi-buddy' ),
				'fields'      => self::shape_fields(),
			],
		];
	}

	/**
	 * All fields flattened: key => field definition (with `group` added).
	 *
	 * @return array<string, array>
	 */
	public static function fields(): array {
		$fields = [];
		foreach ( self::groups() as $group_key => $group ) {
			foreach ( $group['fields'] as $key => $field ) {
				$field['group'] = $group_key;
				$fields[ $key ] = $field;
			}
		}
		return $fields;
	}

	/**
	 * Default value for every field.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array_map( static fn( array $field ) => $field['default'], self::fields() );
	}

	private static function color_fields(): array {
		$builtin = static fn( string $id, string $label, string $help, string $default, string $css ) => [
			'type'    => 'color',
			'label'   => $label,
			'help'    => $help,
			'default' => $default,
			'css'     => $css,
			'divi'    => [ 'kind' => 'customizer_color', 'id' => $id ],
		];
		$extra   = static fn( string $slug, string $label, string $help, string $default ) => [
			'type'       => 'color',
			'label'      => $label,
			'help'       => $help,
			'default'    => $default,
			'css'        => '--dbuddy-color-' . $slug,
			'divi'       => [ 'kind' => 'color', 'id' => 'gcid-dbuddy-' . $slug ],
		];

		// Labels for the 5 built-in colors match the names Divi shows in the builder.
		return [
			'color_primary'   => $builtin( 'gcid-primary-color', __( 'Primary Color', 'divi-buddy' ), __( 'Your main brand color. Used for buttons, links and highlights.', 'divi-buddy' ), '#2ea3f2', '--dbuddy-color-primary' ),
			'color_secondary' => $builtin( 'gcid-secondary-color', __( 'Secondary Color', 'divi-buddy' ), __( 'A supporting brand color that goes well with your primary color.', 'divi-buddy' ), '#2ea3f2', '--dbuddy-color-secondary' ),
			'color_accent'    => $extra( 'accent', __( 'Accent Color', 'divi-buddy' ), __( 'A pop of color for small details that should stand out.', 'divi-buddy' ), '#f2a72e' ),
			'color_heading'   => $builtin( 'gcid-heading-color', __( 'Heading Text Color', 'divi-buddy' ), __( 'The color of titles and headings.', 'divi-buddy' ), '#333333', '--dbuddy-color-heading' ),
			'color_body'      => $builtin( 'gcid-body-color', __( 'Body Text Color', 'divi-buddy' ), __( 'The color of normal paragraph text.', 'divi-buddy' ), '#666666', '--dbuddy-color-body' ),
			'color_link'      => $builtin( 'gcid-link-color', __( 'Link Color', 'divi-buddy' ), __( 'The color of clickable links inside text.', 'divi-buddy' ), '#2ea3f2', '--dbuddy-color-link' ),
			'color_light'     => $extra( 'light', __( 'Light Background Color', 'divi-buddy' ), __( 'A soft background color for light sections.', 'divi-buddy' ), '#f7f7f7' ),
			'color_dark'      => $extra( 'dark', __( 'Dark Background Color', 'divi-buddy' ), __( 'A deep background color for dark sections.', 'divi-buddy' ), '#1f2933' ),
			'color_border'    => $extra( 'border', __( 'Border Color', 'divi-buddy' ), __( 'The color of dividing lines and borders.', 'divi-buddy' ), '#e2e2e2' ),
		];
	}

	private static function typography_fields(): array {
		$weights = [
			'300' => __( 'Light', 'divi-buddy' ),
			'400' => __( 'Regular', 'divi-buddy' ),
			'500' => __( 'Medium', 'divi-buddy' ),
			'600' => __( 'Semi-bold', 'divi-buddy' ),
			'700' => __( 'Bold', 'divi-buddy' ),
			'800' => __( 'Extra bold', 'divi-buddy' ),
			'900' => __( 'Black', 'divi-buddy' ),
		];

		$fields = [
			'font_heading'        => [
				'type'    => 'font',
				'label'   => __( 'Heading font', 'divi-buddy' ),
				'help'    => __( 'The font used for titles and headings.', 'divi-buddy' ),
				'default' => 'Open Sans',
				'css'     => '--dbuddy-font-heading',
				'divi'    => [ 'kind' => 'customizer_font', 'option' => 'heading_font' ],
			],
			'font_body'           => [
				'type'    => 'font',
				'label'   => __( 'Paragraph font', 'divi-buddy' ),
				'help'    => __( 'The font used for normal text.', 'divi-buddy' ),
				'default' => 'Open Sans',
				'css'     => '--dbuddy-font-body',
				'divi'    => [ 'kind' => 'customizer_font', 'option' => 'body_font' ],
			],
			'weight_heading'      => self::variable_field( 'select', 'heading-weight', __( 'Heading thickness', 'divi-buddy' ), __( 'How bold headings look.', 'divi-buddy' ), '700', [ 'options' => $weights, 'apply' => 'heading_weight' ] ),
			'weight_body'         => self::variable_field( 'select', 'body-weight', __( 'Paragraph thickness', 'divi-buddy' ), __( 'How bold normal text looks.', 'divi-buddy' ), '400', [ 'options' => $weights, 'apply' => 'body_weight' ] ),
			'line_height_heading' => self::variable_field( 'number', 'heading-line-height', __( 'Heading line spacing', 'divi-buddy' ), __( 'Space between lines in headings. 1 is tight, 1.5 is roomy.', 'divi-buddy' ), 1.2, [ 'unit' => '', 'min' => 0.8, 'max' => 2.5, 'step' => 0.05, 'apply' => 'heading_line_height' ] ),
			'line_height_body'    => self::variable_field( 'number', 'body-line-height', __( 'Paragraph line spacing', 'divi-buddy' ), __( 'Space between lines in paragraphs. 1.5 to 1.8 is easy to read.', 'divi-buddy' ), 1.6, [ 'unit' => '', 'min' => 1, 'max' => 3, 'step' => 0.05, 'apply' => 'body_line_height' ] ),
		];

		$sizes = [
			'h1'    => [ __( 'Main page title (H1)', 'divi-buddy' ), 56, 36 ],
			'h2'    => [ __( 'Section title (H2)', 'divi-buddy' ), 44, 30 ],
			'h3'    => [ __( 'Sub-title (H3)', 'divi-buddy' ), 34, 26 ],
			'h4'    => [ __( 'Small title (H4)', 'divi-buddy' ), 26, 22 ],
			'h5'    => [ __( 'Smaller title (H5)', 'divi-buddy' ), 21, 19 ],
			'h6'    => [ __( 'Smallest title (H6)', 'divi-buddy' ), 18, 17 ],
			'body'  => [ __( 'Paragraph text', 'divi-buddy' ), 17, 16 ],
			'small' => [ __( 'Small print', 'divi-buddy' ), 14, 13 ],
		];
		foreach ( $sizes as $slug => [ $label, $desktop, $mobile ] ) {
			$fields[ 'size_' . $slug ] = self::variable_field(
				'fluid',
				'size-' . $slug,
				/* translators: %s: text element name, e.g. "Section title (H2)". */
				sprintf( __( '%s size', 'divi-buddy' ), $label ),
				__( 'Text size in pixels on large and small screens.', 'divi-buddy' ),
				[ 'desktop' => $desktop, 'mobile' => $mobile ],
				[ 'unit' => 'px', 'min' => 8, 'max' => 200, 'step' => 1, 'apply' => 'size_' . $slug ]
			);
		}

		return $fields;
	}

	private static function spacing_fields(): array {
		$scale = [
			'xs'  => [ __( 'Extra small space', 'divi-buddy' ), 8, 6 ],
			's'   => [ __( 'Small space', 'divi-buddy' ), 16, 12 ],
			'm'   => [ __( 'Medium space', 'divi-buddy' ), 24, 20 ],
			'l'   => [ __( 'Large space', 'divi-buddy' ), 40, 28 ],
			'xl'  => [ __( 'Extra large space', 'divi-buddy' ), 64, 40 ],
			'xxl' => [ __( 'Huge space', 'divi-buddy' ), 96, 56 ],
		];
		$fields = [];
		foreach ( $scale as $slug => [ $label, $desktop, $mobile ] ) {
			$fields[ 'space_' . $slug ] = self::variable_field(
				'fluid',
				'space-' . $slug,
				$label,
				__( 'A spacing size you can pick in the Divi Builder for padding and margins.', 'divi-buddy' ),
				[ 'desktop' => $desktop, 'mobile' => $mobile ],
				[ 'unit' => 'px', 'min' => 0, 'max' => 400, 'step' => 1 ]
			);
		}

		$fields['section_padding'] = self::variable_field( 'fluid', 'section-padding', __( 'Space above & below sections', 'divi-buddy' ), __( 'The empty space at the top and bottom of each page section.', 'divi-buddy' ), [ 'desktop' => 96, 'mobile' => 56 ], [ 'unit' => 'px', 'min' => 0, 'max' => 400, 'step' => 1 ] );
		$fields['gap']             = self::variable_field( 'fluid', 'gap', __( 'Space between columns', 'divi-buddy' ), __( 'The gap between side-by-side columns and cards.', 'divi-buddy' ), [ 'desktop' => 32, 'mobile' => 20 ], [ 'unit' => 'px', 'min' => 0, 'max' => 200, 'step' => 1 ] );

		return $fields;
	}

	private static function shape_fields(): array {
		return [
			'radius_small'  => self::variable_field( 'number', 'radius-small', __( 'Small corner rounding', 'divi-buddy' ), __( 'For small things like tags and inputs. 0 means square corners.', 'divi-buddy' ), 4, [ 'unit' => 'px', 'min' => 0, 'max' => 100, 'step' => 1 ] ),
			'radius_large'  => self::variable_field( 'number', 'radius-large', __( 'Large corner rounding', 'divi-buddy' ), __( 'For bigger things like cards and images.', 'divi-buddy' ), 16, [ 'unit' => 'px', 'min' => 0, 'max' => 200, 'step' => 1 ] ),
			'radius_button' => self::variable_field( 'number', 'radius-button', __( 'Button corner rounding', 'divi-buddy' ), __( 'How rounded buttons are. Use a big number (like 100) for pill-shaped buttons.', 'divi-buddy' ), 6, [ 'unit' => 'px', 'min' => 0, 'max' => 200, 'step' => 1 ] ),
			'content_width' => self::variable_field( 'number', 'content-width', __( 'Maximum content width', 'divi-buddy' ), __( 'How wide page content can stretch on big screens.', 'divi-buddy' ), 1200, [ 'unit' => 'px', 'min' => 600, 'max' => 2560, 'step' => 10 ] ),
		];
	}

	/**
	 * Build a field that is synced to a Divi "numbers" global variable.
	 */
	private static function variable_field( string $type, string $slug, string $label, string $help, mixed $default, array $extra = [] ): array {
		return array_merge(
			[
				'type'       => $type,
				'label'      => $label,
				'help'       => $help,
				'default'    => $default,
				'css'        => '--dbuddy-' . $slug,
				'divi'       => [ 'kind' => 'variable', 'type' => 'numbers', 'id' => 'gvid-dbuddy-' . $slug ],
			],
			$extra
		);
	}
}
