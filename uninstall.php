<?php
/**
 * Runs when Divi Buddy is deleted from the Plugins screen.
 *
 * Removes Divi Buddy's own options only. Colors, variables and Divi Library sections already sent
 * to Divi are left in place on purpose: pages may still use them.
 *
 * @package DiviBuddy
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

foreach ( [ 'dbuddy_settings', 'dbuddy_version', 'dbuddy_needs_sync', 'dbuddy_sync_status', 'dbuddy_divi_backup', 'dbuddy_library_status', 'dbuddy_library_fingerprint' ] as $option ) {
	delete_option( $option );
}

$role = get_role( 'administrator' );
if ( $role ) {
	$role->remove_cap( 'manage_divi_buddy' );
}
