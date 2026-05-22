<?php
/**
 * Settings form partial.
 *
 * Renders the four configuration fields (domain, selector, enabled,
 * canonicalisation) and the save button.
 *
 * Body wrapped in an immediately-invoked closure to scope locals.
 *
 * @package WPMail_DKIM_Sign
 */

namespace WPMail_DKIM_Sign;

defined( 'ABSPATH' ) || die();

( function ( array $data ): void {
	printf( '<section class="wpdkm-section">' );
	printf( '<h2>%s</h2>', esc_html__( 'Configuration', 'wpmail-dkim-sign' ) );

	printf(
		'<form method="post" action="%s" class="wpdkm-form">',
		esc_url( $data['admin_post'] )
	);

	printf(
		'<input type="hidden" name="action" value="%s" />',
		esc_attr( $data['actions']['save'] )
	);

	wp_nonce_field( $data['actions']['save'], $data['nonces']['save'] );

	printf( '<table class="form-table" role="presentation"><tbody>' );

	/* Domain. */
	printf(
		'<tr><th scope="row"><label for="wpdkm-domain">%s</label></th><td>',
		esc_html__( 'Signing domain', 'wpmail-dkim-sign' )
	);
	printf(
		'<input type="text" id="wpdkm-domain" name="wpdkm_domain" class="regular-text" value="%s" required />',
		esc_attr( $data['settings']['domain'] )
	);
	printf(
		'<p class="description">%s</p>',
		esc_html__( 'The DKIM d= value. Must match the domain in the From: header on outbound mail, so DMARC alignment passes.', 'wpmail-dkim-sign' )
	);
	printf( '</td></tr>' );

	/* Selector. */
	printf(
		'<tr><th scope="row"><label for="wpdkm-selector">%s</label></th><td>',
		esc_html__( 'Selector', 'wpmail-dkim-sign' )
	);
	printf(
		'<input type="text" id="wpdkm-selector" name="wpdkm_selector" class="regular-text" value="%s" required />',
		esc_attr( $data['settings']['selector'] )
	);
	printf(
		'<p class="description">%s</p>',
		esc_html__( 'DNS label used as the subdomain in <selector>._domainkey.<domain>. Lowercase letters, digits, hyphens.', 'wpmail-dkim-sign' )
	);
	printf( '</td></tr>' );

	/* Canonicalisation. */
	printf(
		'<tr><th scope="row"><label for="wpdkm-canon">%s</label></th><td>',
		esc_html__( 'Canonicalisation', 'wpmail-dkim-sign' )
	);
	printf( '<select id="wpdkm-canon" name="wpdkm_canonicalisation">' );
	foreach ( $data['allowed_canon'] as $option ) {
		$selected = ( $option === $data['settings']['canonicalisation'] ) ? ' selected="selected"' : '';
		printf(
			'<option value="%s"%s>%s</option>',
			esc_attr( $option ),
			esc_attr( $selected ),
			esc_html( $option )
		);
	}
	printf( '</select>' );
	printf(
		'<p class="description">%s</p>',
		esc_html__( 'Default relaxed/relaxed tolerates the whitespace and header changes a downstream relay (e.g. Postfix/Amavis) may apply. Use stricter modes only if your relay path does not mutate.', 'wpmail-dkim-sign' )
	);
	printf( '</td></tr>' );

	/* Enabled. */
	$enabled_attr = $data['settings']['enabled'] ? ' checked="checked"' : '';
	printf(
		'<tr><th scope="row">%s</th><td>',
		esc_html__( 'Signing', 'wpmail-dkim-sign' )
	);
	printf( '<label for="wpdkm-enabled">' );
	printf(
		'<input type="checkbox" id="wpdkm-enabled" name="wpdkm_enabled" value="1"%s />',
		esc_attr( $enabled_attr )
	);
	printf( ' %s</label>', esc_html__( 'Enabled — sign outbound mail with DKIM', 'wpmail-dkim-sign' ) );
	printf(
		'<p class="description">%s</p>',
		esc_html__( 'Leave off until the DNS TXT record is live and verified. With this off the plugin is a no-op.', 'wpmail-dkim-sign' )
	);
	printf( '</td></tr>' );

	printf( '</tbody></table>' );

	submit_button( __( 'Save settings', 'wpmail-dkim-sign' ) );

	printf( '</form>' );
	printf( '</section>' );
} )( $data );
