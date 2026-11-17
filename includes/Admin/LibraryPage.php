<?php
/**
 * "Divi Buddy → Section Library" admin page: lists the library sections and their status on this site.
 *
 * @package DiviBuddy
 */

namespace DiviBuddy\Admin;

use DiviBuddy\Library\LibrarySync;
use DiviBuddy\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Section Library page.
 */
final class LibraryPage {

	public const SLUG        = 'divi-buddy-library';
	private const NOTICE_ARG = 'dbuddy_notice';
	private string $hook_suffix = '';

	public function __construct( private LibrarySync $library ) {}

	public function register(): void {
		add_action( 'admin_menu', [ $this, 'add_menu' ], 20 ); // After the parent Divi Buddy menu exists.
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
		add_action( 'admin_post_dbuddy_library_sync', [ $this, 'handle_sync' ] );
		add_action( 'admin_post_dbuddy_library_restore', [ $this, 'handle_restore' ] );
	}

	public function add_menu(): void {
		$this->hook_suffix = (string) add_submenu_page(
			SettingsPage::SLUG,
			__( 'Divi Buddy — Section Library', 'divi-buddy' ),
			__( 'Section Library', 'divi-buddy' ),
			Plugin::CAPABILITY,
			self::SLUG,
			[ $this, 'render' ]
		);
	}

	public function enqueue( string $hook_suffix ): void {
		if ( $hook_suffix === $this->hook_suffix ) {
			wp_enqueue_style( 'dbuddy-admin', DBUDDY_URL . 'assets/admin/css/settings.css', [], DBUDDY_VERSION );
		}
	}

	/* ---------------------------------------------------------------------
	 * Actions
	 * ------------------------------------------------------------------- */

	public function handle_sync(): void {
		$this->guard( 'dbuddy_library_sync' );
		$this->library->run();
		$this->redirect( 'library_synced' );
	}

	public function handle_restore(): void {
		$this->guard( 'dbuddy_library_restore' );
		$id = isset( $_POST['section'] ) ? sanitize_key( wp_unslash( $_POST['section'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Checked in guard().
		if ( '' !== $id ) {
			$this->library->restore( $id );
		}
		$this->redirect( 'library_restored' );
	}

	private function guard( string $action ): void {
		if ( ! current_user_can( Plugin::CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'divi-buddy' ), 403 );
		}
		check_admin_referer( $action );
	}

	private function redirect( string $notice ): void {
		wp_safe_redirect( add_query_arg( self::NOTICE_ARG, $notice, admin_url( 'admin.php?page=' . self::SLUG ) ) );
		exit;
	}

	/* ---------------------------------------------------------------------
	 * Rendering
	 * ------------------------------------------------------------------- */

	public function render(): void {
		if ( ! current_user_can( Plugin::CAPABILITY ) ) {
			return;
		}

		$items  = $this->library->items();
		$status = LibrarySync::last_status();
		$labels = [
			'up_to_date'     => __( 'Ready to use', 'divi-buddy' ),
			'update_pending' => __( 'Update waiting', 'divi-buddy' ),
			'modified'       => __( 'Changed on this site', 'divi-buddy' ),
			'deleted'        => __( 'Deleted on this site', 'divi-buddy' ),
			'not_added'      => __( 'Not added yet', 'divi-buddy' ),
		];
		?>
		<div class="wrap dbuddy">
			<h1><?php esc_html_e( 'Section Library', 'divi-buddy' ); ?></h1>
			<p class="dbuddy-intro">
				<?php esc_html_e( 'Ready-made sections you can add to any page. In the Divi Builder, add a section from the Divi Library and look in the "Divi Buddy" category. Images in these sections are placeholders: swap in your own after adding one.', 'divi-buddy' ); ?>
			</p>

			<?php $this->render_notices( $status ); ?>

			<?php if ( ! $items ) : ?>
				<p><em><?php esc_html_e( 'There are no sections in the library yet.', 'divi-buddy' ); ?></em></p>
			<?php else : ?>
				<table class="widefat striped dbuddy-library-table">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Section', 'divi-buddy' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Status', 'divi-buddy' ); ?></th>
							<th scope="col" class="dbuddy-library-actions"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'divi-buddy' ); ?></span></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $items as $id => $item ) : ?>
							<tr>
								<td><?php echo esc_html( $item['title'] ); ?></td>
								<td>
									<span class="dbuddy-state dbuddy-state--<?php echo esc_attr( $item['state'] ); ?>"><?php echo esc_html( $labels[ $item['state'] ] ?? $item['state'] ); ?></span>
									<?php if ( 'modified' === $item['state'] ) : ?>
										<br /><span class="description"><?php esc_html_e( 'Someone edited this section in the Divi Library, so updates from Divi Buddy are paused for it.', 'divi-buddy' ); ?></span>
									<?php endif; ?>
								</td>
								<td class="dbuddy-library-actions">
									<?php if ( in_array( $item['state'], [ 'modified', 'deleted' ], true ) ) : ?>
										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="dbuddy-restore-form">
											<input type="hidden" name="action" value="dbuddy_library_restore" />
											<input type="hidden" name="section" value="<?php echo esc_attr( $id ); ?>" />
											<?php wp_nonce_field( 'dbuddy_library_restore' ); ?>
											<button type="submit" class="button button-small"><?php esc_html_e( 'Restore original', 'divi-buddy' ); ?></button>
										</form>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="dbuddy-inline-form">
				<input type="hidden" name="action" value="dbuddy_library_sync" />
				<?php wp_nonce_field( 'dbuddy_library_sync' ); ?>
				<?php submit_button( __( 'Update section library', 'divi-buddy' ), 'secondary', 'submit', false ); ?>
				<span class="description"><?php esc_html_e( 'This happens automatically after each Divi Buddy update. Use it if a section is missing from the Divi Library.', 'divi-buddy' ); ?></span>
			</form>
		</div>
		<?php
	}

	private function render_notices( ?array $status ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only flag set by our own redirects.
		$notice = isset( $_GET[ self::NOTICE_ARG ] ) ? sanitize_key( $_GET[ self::NOTICE_ARG ] ) : '';

		if ( $status && $status['errors'] ) {
			printf( '<div class="notice notice-warning"><p>%s</p></div>', esc_html( implode( ' ', $status['errors'] ) ) );
			return;
		}

		if ( $status && in_array( $notice, [ 'library_synced', 'library_restored' ], true ) ) {
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: 1: added count, 2: updated count. */
						__( 'Section library is up to date (%1$d added, %2$d updated).', 'divi-buddy' ),
						$status['created'],
						$status['updated']
					)
				)
			);
		}
	}
}
