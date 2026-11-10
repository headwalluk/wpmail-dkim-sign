<?php
/**
 * Plugin Name:       WP Mail DKIM Sign
 * Plugin URI:        https://headwall-hosting.com/wpmail-dkim-sign
 * Description:       DKIM-signs outbound wp_mail() messages at the WordPress layer, so the DKIM-Signature header is attached before the message leaves the site for the upstream MTA. RSA-2048, single signing domain.
 * Version:           1.1.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Headwall Hosting
 * Author URI:        https://headwall-hosting.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wpmail-dkim-sign
 * Domain Path:       /languages
 *
 * @package WPMail_DKIM_Sign
 */

defined( 'ABSPATH' ) || die();

define( 'WPDKM_VERSION', '1.1.0' );
define( 'WPDKM_FILE', __FILE__ );
define( 'WPDKM_PATH', plugin_dir_path( __FILE__ ) );
define( 'WPDKM_URL', plugin_dir_url( __FILE__ ) );
define( 'WPDKM_BASENAME', plugin_basename( __FILE__ ) );

require_once __DIR__ . '/constants.php';
require_once __DIR__ . '/functions-private.php';

require_once __DIR__ . '/includes/class-settings.php';
require_once __DIR__ . '/includes/class-keypair.php';
require_once __DIR__ . '/includes/class-signer.php';
require_once __DIR__ . '/includes/class-verifier.php';
require_once __DIR__ . '/includes/class-plugin.php';

/**
 * Bootstrap the plugin.
 *
 * Constructs the orchestrator, stores it on the global accessor
 * variable for sub-services to reach via WPMail_DKIM_Sign\get_plugin(),
 * and registers hooks.
 *
 * @since 1.0.0
 *
 * @return void
 */
function wpdkm_run(): void {
	global $wpdkm_plugin;

	$wpdkm_plugin = new \WPMail_DKIM_Sign\Plugin();
	$wpdkm_plugin->run();
}

wpdkm_run();

// GitHub auto-updates (admin, cron and WP-CLI only; front-end requests never check for updates).
if ( is_admin() || ( defined( 'DOING_CRON' ) && DOING_CRON ) || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
	require_once __DIR__ . '/includes/class-github-updater.php';
	new \WPMail_DKIM_Sign\Github_Updater( WPDKM_FILE, \WPMail_DKIM_Sign\UPDATER_GITHUB_REPO, WPDKM_VERSION, 'wpdkm_updater_enabled' );
}
