<?php
/**
 * Admin notice partial.
 *
 * Reads $data['notice'] (a NOTICE_* identifier string passed via
 * query arg) and renders a WordPress-style notice block.
 *
 * Body wrapped in an immediately-invoked closure to scope locals.
 *
 * @package WPMail_DKIM_Sign
 */

namespace WPMail_DKIM_Sign;

defined( 'ABSPATH' ) || die();

( function ( array $data ): void {
	$notice_map = array(
		NOTICE_SAVED                  => array(
			'kind' => 'success',
			'text' => __( 'Settings saved.', 'wpmail-dkim-sign' ),
		),
		NOTICE_INVALID                => array(
			'kind' => 'error',
			'text' => __( 'Some inputs were not valid. Please review the form and try again.', 'wpmail-dkim-sign' ),
		),
		NOTICE_KEY_GENERATED          => array(
			'kind' => 'success',
			'text' => __( 'New key pair generated. Publish the DNS TXT record shown below, then tick "Enabled".', 'wpmail-dkim-sign' ),
		),
		NOTICE_KEY_REPLACED           => array(
			'kind' => 'success',
			'text' => __( 'Key pair replaced. You must republish the DNS TXT record — the old record no longer matches.', 'wpmail-dkim-sign' ),
		),
		NOTICE_KEY_DELETED            => array(
			'kind' => 'success',
			'text' => __( 'Key pair deleted. Signing is now disabled until a new key is generated.', 'wpmail-dkim-sign' ),
		),
		NOTICE_KEY_ERROR              => array(
			'kind' => 'error',
			'text' => __( 'Key generation failed. Check the PHP error log for OpenSSL details.', 'wpmail-dkim-sign' ),
		),
		NOTICE_TEST_SENT              => array(
			'kind' => 'success',
			'text' => __( 'Test email sent. See the captured headers and DKIM-Signature check below.', 'wpmail-dkim-sign' ),
		),
		NOTICE_TEST_FAILED            => array(
			'kind' => 'error',
			'text' => __( 'wp_mail() returned false. The captured headers below (if any) show the prepared message; the failure is most likely at the SMTP layer.', 'wpmail-dkim-sign' ),
		),
		NOTICE_TEST_INVALID_RECIPIENT => array(
			'kind' => 'error',
			'text' => __( 'The recipient address is not a valid email. Please correct it and try again.', 'wpmail-dkim-sign' ),
		),
		NOTICE_DNS_CHECKED            => array(
			'kind' => 'info',
			'text' => __( 'DNS lookup complete. See the result below.', 'wpmail-dkim-sign' ),
		),
	);

	if ( ! isset( $notice_map[ $data['notice'] ] ) ) {
		// Unknown notice identifier — render nothing rather than expose internal state.
	} else {
		$entry = $notice_map[ $data['notice'] ];
		printf(
			'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
			esc_attr( $entry['kind'] ),
			esc_html( $entry['text'] )
		);
	}
} )( $data );
