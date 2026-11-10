<?php
/**
 * Plugin uninstall handler.
 *
 * Removes plugin data from the database only when the user has
 * explicitly opted in via the "Remove all data on uninstall"
 * checkbox in the settings page. Deactivation alone never removes
 * data — uninstall (deletion) does, and even then only on opt-in.
 *
 * This file runs in a separate request from the rest of the plugin.
 * Only the updater class is loaded, for its cache helper, so option
 * keys are written as string literals rather than namespaced constants.
 *
 * @package WPMail_DKIM_Sign
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || die();

require_once __DIR__ . '/includes/class-github-updater.php';

$wpdkm_settings = get_option( 'wpdkm_settings', array() );

if ( ! empty( $wpdkm_settings['remove_on_uninstall'] ) ) {
	delete_option( 'wpdkm_settings' );
	delete_option( 'wpdkm_keypair' );
	\WPMail_DKIM_Sign\Github_Updater::delete_cache( basename( __DIR__ ) );
} else {
	// User did not opt in to data removal — leave options in place.
}
