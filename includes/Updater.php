<?php
/**
 * Self-updates from GitHub Releases using the bundled Plugin Update Checker library (v5.7, MIT).
 *
 * - Only the `divi-buddy.zip` asset attached to a GitHub Release is installed (never the raw
 *   source zip, which would include tests and dev files).
 * - Private repo: define DBUDDY_GITHUB_TOKEN in wp-config.php (a fine-grained, read-only token
 *   scoped to this one repo). Public repo: leave it undefined. No code change needed to switch.
 *
 * See docs/releasing.md.
 *
 * @package DiviBuddy
 */

namespace DiviBuddy;

defined( 'ABSPATH' ) || exit;

/**
 * GitHub updater.
 */
final class Updater {

	public const REPO_URL   = 'https://github.com/austintackettdev/divi-buddy/';
	public const ASSET_NAME = 'divi-buddy.zip';

	public function register(): void {
		if ( self::is_dev_checkout() ) {
			return;
		}

		$loader = DBUDDY_PATH . 'lib/plugin-update-checker/plugin-update-checker.php';
		if ( ! is_readable( $loader ) ) {
			return;
		}
		require_once $loader;

		if ( ! class_exists( '\YahnisElsts\PluginUpdateChecker\v5\PucFactory' ) ) {
			return;
		}

		/**
		 * GitHub repository Divi Buddy updates from.
		 *
		 * @param string $url Repository URL.
		 */
		$repo = (string) apply_filters( 'dbuddy_update_repo_url', self::REPO_URL );

		$checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker( $repo, DBUDDY_FILE, 'divi-buddy' );
		$checker->setBranch( 'main' );

		if ( defined( 'DBUDDY_GITHUB_TOKEN' ) && is_string( DBUDDY_GITHUB_TOKEN ) && '' !== DBUDDY_GITHUB_TOKEN ) {
			$checker->setAuthentication( DBUDDY_GITHUB_TOKEN );
		}

		$api = $checker->getVcsApi();
		if ( method_exists( $api, 'enableReleaseAssets' ) ) {
			// Api::REQUIRE_RELEASE_ASSETS (= 2): ignore releases without a matching zip. The constant
			// lives in a minor-version namespace (v5p7), so read it defensively in case the library is upgraded.
			$require_const = '\YahnisElsts\PluginUpdateChecker\v5p7\Vcs\Api::REQUIRE_RELEASE_ASSETS';
			$api->enableReleaseAssets(
				'/^' . preg_quote( self::ASSET_NAME, '/' ) . '$/',
				defined( $require_const ) ? constant( $require_const ) : 2
			);
		}
	}

	/**
	 * True when running from a git checkout (the dev site links the plugin folder to the project).
	 *
	 * Updating there would be destructive: WordPress deletes the old plugin folder before installing
	 * the new one, which would wipe the project's working copy through the link. Release zips never
	 * contain `.git`, so live sites are unaffected.
	 */
	public static function is_dev_checkout(): bool {
		/**
		 * Override development-checkout detection (e.g. to test the updater on a throwaway copy).
		 *
		 * @param bool $is_dev Whether the plugin runs from a git checkout.
		 */
		return (bool) apply_filters( 'dbuddy_is_dev_checkout', file_exists( DBUDDY_PATH . '.git' ) );
	}
}
