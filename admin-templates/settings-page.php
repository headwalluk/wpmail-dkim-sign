<?php
/**
 * Settings page top-level template.
 *
 * Receives a $data array from Settings::render_page() with keys:
 * - page_title, settings, keypair_meta, canonicalisation, notice
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
if ( ! is_null( $data['dns_record'] ) ) {
	require WPDKM_PATH . 'admin-templates/partials/dns-record.php';
} else {
	// No key stored — DNS record block is hidden until generation completes.
}
printf( '</div>' );

printf( '<div id="verify-panel" class="tab-panel" style="display:none;">' );
require WPDKM_PATH . 'admin-templates/partials/verify-test-email.php';
require WPDKM_PATH . 'admin-templates/partials/verify-dns-check.php';
printf( '</div>' );

printf( '</div>' );
printf( '</div>' );
