<?php
/**
 * The "Divi Buddy" admin page where clients edit site-wide design settings.
 *
 * @package DiviBuddy
 */

namespace DiviBuddy\Admin;

use DiviBuddy\Divi\Divi;
use DiviBuddy\Divi\VariablesSync;
use DiviBuddy\Plugin;
use DiviBuddy\Settings\Schema;
use DiviBuddy\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Settings page.
 */
final class SettingsPage {

	public const SLUG         = 'divi-buddy';
	private const GROUP       = 'dbuddy';
	private const NOTICE_ARG  = 'dbuddy_notice';
	private string $hook_suffix = '';

	public function __construct( private VariablesSync $sync ) {}

	public function register(): void {
		add_action( 'admin_menu', [ $this, 'add_menu' ] );
		add_action( 'admin_init', [ $this, 'register_setting' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
		add_action( 'admin_post_dbuddy_resync', [ $this, 'handle_resync' ] );
		add_action( 'admin_post_dbuddy_reset', [ $this, 'handle_reset' ] );
		add_filter( 'option_page_capability_' . self::GROUP, static fn() => Plugin::CAPABILITY );
		add_filter( 'plugin_action_links_' . plugin_basename( DBUDDY_FILE ), [ $this, 'action_links' ] );
	}

	public function add_menu(): void {
		$this->hook_suffix = (string) add_menu_page(
			__( 'Divi Buddy — Site Design', 'divi-buddy' ),
			__( 'Divi Buddy', 'divi-buddy' ),
			Plugin::CAPABILITY,
			self::SLUG,
			[ $this, 'render' ],
			'dashicons-art',
			59
		);
	}

	public function register_setting(): void {
		register_setting(
			self::GROUP,
			Settings::OPTION,
			[
				'type'              => 'array',
				'sanitize_callback' => [ Plugin::settings(), 'sanitize_option' ],
				'show_in_rest'      => false,
			]
		);
	}

	public function action_links( array $links ): array {
		if ( current_user_can( Plugin::CAPABILITY ) ) {
			array_unshift(
				$links,
				sprintf( '<a href="%s">%s</a>', esc_url( $this->page_url() ), esc_html__( 'Settings', 'divi-buddy' ) )
			);
		}
		return $links;
	}

	public function enqueue( string $hook_suffix ): void {
		if ( $hook_suffix !== $this->hook_suffix ) {
			return;
		}
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style( 'dbuddy-admin', DBUDDY_URL . 'assets/admin/css/settings.css', [], DBUDDY_VERSION );
		wp_enqueue_script( 'dbuddy-admin', DBUDDY_URL . 'assets/admin/js/settings.js', [ 'jquery', 'wp-color-picker' ], DBUDDY_VERSION, true );
		wp_localize_script(
			'dbuddy-admin',
			'DiviBuddy',
			[
				'i18n' => [
					'confirmResetTab' => __( 'Put every setting on this tab back to the Divi Buddy default? Nothing is saved until you click "Save changes".', 'divi-buddy' ),
					'confirmResetAll' => __( 'Reset ALL Divi Buddy settings to their defaults and update Divi right away? This cannot be undone.', 'divi-buddy' ),
					'unsaved'         => __( 'You have unsaved changes.', 'divi-buddy' ),
				],
			]
		);
	}

	/* ---------------------------------------------------------------------
	 * Actions
	 * ------------------------------------------------------------------- */

	public function handle_resync(): void {
		$this->guard( 'dbuddy_resync' );
		$status = $this->sync->run();
		if ( $status['ok'] ) {
			delete_option( VariablesSync::NEEDS_SYNC_OPTION );
		}
		$this->redirect( 'resynced' );
	}

	public function handle_reset(): void {
		$this->guard( 'dbuddy_reset' );
		Plugin::settings()->reset();
		// If the reset didn't change the stored value, WordPress skips the update hook, so sync explicitly.
		$this->sync->run();
		$this->redirect( 'reset' );
	}

	private function guard( string $action ): void {
		if ( ! current_user_can( Plugin::CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'divi-buddy' ), 403 );
		}
		check_admin_referer( $action );
	}

	private function redirect( string $notice ): void {
		wp_safe_redirect( add_query_arg( self::NOTICE_ARG, $notice, $this->page_url() ) );
		exit;
	}

	private function page_url(): string {
		return admin_url( 'admin.php?page=' . self::SLUG );
	}

	/* ---------------------------------------------------------------------
	 * Rendering
	 * ------------------------------------------------------------------- */

	public function render(): void {
		if ( ! current_user_can( Plugin::CAPABILITY ) ) {
			return;
		}

		$values   = Plugin::settings()->values();
		$defaults = Schema::defaults();
		$groups   = Schema::groups();
		$fonts    = Divi::font_names();
		?>
		<div class="wrap dbuddy">
			<h1><?php esc_html_e( 'Divi Buddy — Site Design', 'divi-buddy' ); ?></h1>
			<p class="dbuddy-intro">
				<?php esc_html_e( 'Set your colors, fonts and spacing here once, and they apply across the whole site. They also show up in the Divi Builder, so everything you build matches.', 'divi-buddy' ); ?>
			</p>

			<?php $this->render_notices(); ?>

			<form method="post" action="options.php" id="dbuddy-form">
				<?php settings_fields( self::GROUP ); ?>

				<nav class="dbuddy-tabs" role="tablist">
					<?php foreach ( $groups as $group_key => $group ) : ?>
						<button type="button" class="dbuddy-tab" role="tab" data-tab="<?php echo esc_attr( $group_key ); ?>" aria-controls="dbuddy-panel-<?php echo esc_attr( $group_key ); ?>">
							<?php echo esc_html( $group['label'] ); ?>
						</button>
					<?php endforeach; ?>
				</nav>

				<?php foreach ( $groups as $group_key => $group ) : ?>
					<section class="dbuddy-panel" id="dbuddy-panel-<?php echo esc_attr( $group_key ); ?>" role="tabpanel" data-panel="<?php echo esc_attr( $group_key ); ?>">
						<p class="dbuddy-panel-description"><?php echo esc_html( $group['description'] ); ?></p>

						<div class="dbuddy-fields">
							<?php
							foreach ( $group['fields'] as $key => $field ) {
								$this->render_field( $key, $field, $values[ $key ] ?? $field['default'], $defaults[ $key ], $fonts );
							}
							?>
						</div>

						<p class="dbuddy-panel-actions">
							<button type="button" class="button-link dbuddy-reset-tab" data-tab="<?php echo esc_attr( $group_key ); ?>">
								<?php
								/* translators: %s: tab name, e.g. "Colors". */
								echo esc_html( sprintf( __( 'Reset %s to defaults', 'divi-buddy' ), $group['label'] ) );
								?>
							</button>
						</p>
					</section>
				<?php endforeach; ?>

				<div class="dbuddy-savebar">
					<?php submit_button( __( 'Save changes', 'divi-buddy' ), 'primary large', 'submit', false ); ?>
					<span class="dbuddy-unsaved" hidden><?php esc_html_e( 'You have unsaved changes.', 'divi-buddy' ); ?></span>
				</div>
			</form>

			<?php $this->render_tools(); ?>
		</div>
		<?php
	}

	private function render_notices(): void {
		settings_errors();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only flags set by our own redirects.
		$notice  = isset( $_GET[ self::NOTICE_ARG ] ) ? sanitize_key( $_GET[ self::NOTICE_ARG ] ) : '';
		$updated = isset( $_GET['settings-updated'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$status  = VariablesSync::last_status();

		if ( 'reset' === $notice ) {
			$this->notice( 'info', __( 'All settings were reset to their defaults.', 'divi-buddy' ) );
		}

		if ( $status && ! $status['ok'] ) {
			$this->notice( 'error', $status['message'] . ' ' . __( 'Try "Send settings to Divi again" below. If it keeps happening, contact your web designer.', 'divi-buddy' ) );
		} elseif ( $status && ( $updated || $notice ) ) {
			$this->notice( 'success', $status['message'] );
		}
	}

	private function notice( string $type, string $message ): void {
		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $type ),
			esc_html( $message )
		);
	}

	private function render_field( string $key, array $field, mixed $value, mixed $default, ?array $fonts ): void {
		$id   = 'dbuddy-' . $key;
		$name = Settings::OPTION . '[values][' . $key . ']';
		?>
		<div class="dbuddy-field dbuddy-field--<?php echo esc_attr( $field['type'] ); ?>" data-key="<?php echo esc_attr( $key ); ?>" data-default="<?php echo esc_attr( (string) wp_json_encode( $default ) ); ?>">
			<div class="dbuddy-field-label">
				<label for="<?php echo esc_attr( 'fluid' === $field['type'] ? $id . '-desktop' : $id ); ?>"><?php echo esc_html( $field['label'] ); ?></label>
				<p class="description"><?php echo esc_html( $field['help'] ); ?></p>
			</div>
			<div class="dbuddy-field-control">
				<?php
				switch ( $field['type'] ) {
					case 'color':
						printf(
							'<input type="text" class="dbuddy-color" id="%1$s" name="%2$s" value="%3$s" data-default-color="%4$s" />',
							esc_attr( $id ),
							esc_attr( $name ),
							esc_attr( (string) $value ),
							esc_attr( (string) $default )
						);
						break;

					case 'font':
						$this->render_font_select( $id, $name, (string) $value, $fonts );
						break;

					case 'select':
						printf( '<select id="%1$s" name="%2$s">', esc_attr( $id ), esc_attr( $name ) );
						foreach ( $field['options'] as $option_value => $option_label ) {
							printf(
								'<option value="%1$s" %2$s>%3$s</option>',
								esc_attr( (string) $option_value ),
								selected( (string) $value, (string) $option_value, false ),
								esc_html( $option_label )
							);
						}
						echo '</select>';
						break;

					case 'number':
						$this->render_number( $id, $name, $value, $field );
						break;

					case 'fluid':
						$value = is_array( $value ) ? $value : $field['default'];
						echo '<div class="dbuddy-fluid">';
						echo '<span class="dbuddy-fluid-part"><span class="dbuddy-fluid-label">' . esc_html__( 'Big screens', 'divi-buddy' ) . '</span>';
						$this->render_number( $id . '-desktop', $name . '[desktop]', $value['desktop'], $field, 'desktop' );
						echo '</span>';
						echo '<span class="dbuddy-fluid-part"><span class="dbuddy-fluid-label">' . esc_html__( 'Phones', 'divi-buddy' ) . '</span>';
						$this->render_number( $id . '-mobile', $name . '[mobile]', $value['mobile'], $field, 'mobile' );
						echo '</span>';
						echo '</div>';
						if ( str_starts_with( $key, 'size_' ) ) {
							printf(
								'<div class="dbuddy-preview" aria-hidden="true"><span class="dbuddy-preview-desktop" style="font-size:%1$spx">Aa</span><span class="dbuddy-preview-mobile" style="font-size:%2$spx">Aa</span></div>',
								esc_attr( (string) $value['desktop'] ),
								esc_attr( (string) $value['mobile'] )
							);
						}
						break;
				}
				?>
			</div>
		</div>
		<?php
	}

	private function render_number( string $id, string $name, mixed $value, array $field, string $part = '' ): void {
		printf(
			'<span class="dbuddy-number"><input type="number" id="%1$s" name="%2$s" value="%3$s" min="%4$s" max="%5$s" step="%6$s" data-part="%7$s" class="small-text" />%8$s</span>',
			esc_attr( $id ),
			esc_attr( $name ),
			esc_attr( (string) $value ),
			esc_attr( (string) ( $field['min'] ?? '' ) ),
			esc_attr( (string) ( $field['max'] ?? '' ) ),
			esc_attr( (string) ( $field['step'] ?? 'any' ) ),
			esc_attr( $part ),
			! empty( $field['unit'] ) ? '<span class="dbuddy-unit">' . esc_html( $field['unit'] ) . '</span>' : ''
		);
	}

	/**
	 * @param string[]|null $fonts
	 */
	private function render_font_select( string $id, string $name, string $value, ?array $fonts ): void {
		if ( null === $fonts ) {
			printf( '<input type="text" class="regular-text" id="%1$s" name="%2$s" value="%3$s" />', esc_attr( $id ), esc_attr( $name ), esc_attr( $value ) );
			return;
		}
		if ( ! in_array( $value, $fonts, true ) ) {
			array_unshift( $fonts, $value );
		}
		printf( '<select class="dbuddy-font" id="%1$s" name="%2$s">', esc_attr( $id ), esc_attr( $name ) );
		foreach ( $fonts as $font ) {
			printf( '<option value="%1$s" %2$s>%1$s</option>', esc_attr( $font ), selected( $value, $font, false ) );
		}
		echo '</select>';
	}

	private function render_tools(): void {
		$status = VariablesSync::last_status();
		?>
		<div class="dbuddy-tools">
			<h2><?php esc_html_e( 'Tools', 'divi-buddy' ); ?></h2>

			<p class="dbuddy-status">
				<?php
				if ( $status ) {
					printf(
						/* translators: 1: date/time, 2: "worked" or "failed". */
						esc_html__( 'Last sent to Divi: %1$s (%2$s).', 'divi-buddy' ),
						esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $status['time'] ) ),
						$status['ok'] ? esc_html__( 'worked', 'divi-buddy' ) : '<strong>' . esc_html__( 'failed', 'divi-buddy' ) . '</strong>'
					);
				} else {
					esc_html_e( 'Settings have not been sent to Divi yet.', 'divi-buddy' );
				}
				?>
			</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="dbuddy-inline-form">
				<input type="hidden" name="action" value="dbuddy_resync" />
				<?php wp_nonce_field( 'dbuddy_resync' ); ?>
				<?php submit_button( __( 'Send settings to Divi again', 'divi-buddy' ), 'secondary', 'submit', false ); ?>
				<span class="description"><?php esc_html_e( 'Use this if colors or sizes in the Divi Builder don\'t match what\'s shown here.', 'divi-buddy' ); ?></span>
			</form>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="dbuddy-inline-form" id="dbuddy-reset-all">
				<input type="hidden" name="action" value="dbuddy_reset" />
				<?php wp_nonce_field( 'dbuddy_reset' ); ?>
				<?php submit_button( __( 'Reset everything to defaults', 'divi-buddy' ), 'delete', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}
}
