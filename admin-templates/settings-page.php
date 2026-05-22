<?php
/**
 * Settings page top-level template.
 *
 * Receives a $data array from Settings::render_page() with keys:
 * - page_title, settings, keypair_meta, allowed_canon, notice
 * - admin_post, actions[*], nonces[*]
 *
 * @package WPMail_DKIM_Sign
 */

namespace WPMail_DKIM_Sign;

defined( 'ABSPATH' ) || die();

printf( '<div class="wrap wpdkm-settings">' );
printf( '<h1>%s</h1>', esc_html( $data['page_title'] ) );

if ( '' !== $data['notice'] ) {
	require WPDKM_PATH . 'admin-templates/partials/notice.php';
} else {
	// No notice to render.
}

require WPDKM_PATH . 'admin-templates/partials/tab-nav.php';

printf( '<div class="tab-content">' );

printf( '<div id="setup-panel" class="tab-panel">' );
require WPDKM_PATH . 'admin-templates/partials/settings-form.php';
require WPDKM_PATH . 'admin-templates/partials/key-management.php';
printf( '</div>' );

printf( '<div id="verify-panel" class="tab-panel" style="display:none;">' );
printf(
	'<p>%s</p>',
	esc_html__( 'Verification tools (test email and DNS lookup) will arrive in a future release.', 'wpmail-dkim-sign' )
);
printf( '</div>' );

printf( '</div>' );
printf( '</div>' );
