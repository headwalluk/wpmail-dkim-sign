<?php
/**
 * Verify-tab: Send Test Email partial.
 *
 * Renders the recipient input + Send button, plus (when a recent
 * capture exists in the transient) the captured headers and a
 * diagnostic verdict on whether a DKIM-Signature was attached.
 *
 * Body wrapped in an immediately-invoked closure to scope locals.
 *
 * @package WPMail_DKIM_Sign
 */

namespace WPMail_DKIM_Sign;

defined( 'ABSPATH' ) || die();

( function ( array $data ): void {
	$result     = $data['test_result'];
	$default_to = ( ! is_null( $result ) && ! empty( $result['recipient'] ) )
		? (string) $result['recipient']
		: $data['admin_email'];

	printf( '<section class="wpdkm-section">' );
	printf( '<h2>%s</h2>', esc_html__( 'Send test email', 'wpmail-dkim-sign' ) );
	printf(
		'<p>%s</p>',
		esc_html__( 'Sends a short test message from wpdkm-test@<your-signing-domain> to the address below. The prepared headers (after signing) are captured and shown here, plus a diagnostic check on the DKIM-Signature line. The most reliable real-world test is sending to an external mailbox you control (e.g. an outlook.com address) and inspecting the Authentication-Results header at the receiver.', 'wpmail-dkim-sign' )
	);

	printf( '<form method="post" action="%s" class="wpdkm-form">', esc_url( $data['admin_post'] ) );
	printf( '<input type="hidden" name="action" value="%s" />', esc_attr( $data['actions']['test_email'] ) );
	wp_nonce_field( $data['actions']['test_email'], $data['nonces']['test_email'] );

	printf( '<table class="form-table" role="presentation"><tbody>' );
	printf(
		'<tr><th scope="row"><label for="wpdkm-recipient">%s</label></th><td>',
		esc_html__( 'Recipient', 'wpmail-dkim-sign' )
	);
	printf(
		'<input type="email" id="wpdkm-recipient" name="wpdkm_recipient" class="regular-text" value="%s" required />',
		esc_attr( $default_to )
	);
	printf(
		'<p class="description">%s</p>',
		esc_html__( 'Defaults to the WordPress admin email. Change to any external mailbox you can read.', 'wpmail-dkim-sign' )
	);
	printf( '</td></tr>' );
	printf( '</tbody></table>' );

	printf(
		'<p><button type="submit" class="button button-primary">%s</button></p>',
		esc_html__( 'Send test email', 'wpmail-dkim-sign' )
	);
	printf( '</form>' );

	if ( is_null( $result ) ) {
		// No recent test — result panel hidden.
	} else {
		printf( '<div class="wpdkm-result-panel">' );
		printf( '<h3>%s</h3>', esc_html__( 'Last test result', 'wpmail-dkim-sign' ) );

		printf( '<ul class="wpdkm-result-meta">' );
		printf(
			'<li><strong>%s:</strong> %s</li>',
			esc_html__( 'Sent at', 'wpmail-dkim-sign' ),
			esc_html( (string) ( $result['sent_at'] ?? '' ) )
		);
		printf(
			'<li><strong>%s:</strong> %s</li>',
			esc_html__( 'Recipient', 'wpmail-dkim-sign' ),
			esc_html( (string) ( $result['recipient'] ?? '' ) )
		);
		printf(
			'<li><strong>%s:</strong> %s</li>',
			esc_html__( 'wp_mail() returned', 'wpmail-dkim-sign' ),
			! empty( $result['wp_mail_returned'] )
				? esc_html__( 'true', 'wpmail-dkim-sign' )
				: esc_html__( 'false (delivery failed at the SMTP layer; headers captured before send)', 'wpmail-dkim-sign' )
		);
		printf( '</ul>' );

		if ( ! empty( $result['dkim_signature'] ) ) {
			printf(
				'<p class="wpdkm-verdict wpdkm-verdict-good"><strong>%s</strong> %s</p>',
				esc_html__( 'DKIM-Signature attached.', 'wpmail-dkim-sign' ),
				esc_html__( 'The header is present in the prepared message:', 'wpmail-dkim-sign' )
			);
			printf( '<pre class="wpdkm-headers">%s</pre>', esc_html( (string) $result['dkim_signature'] ) );
		} else {
			printf(
				'<p class="wpdkm-verdict wpdkm-verdict-bad"><strong>%s</strong> %s</p>',
				esc_html__( 'No DKIM-Signature in the prepared message.', 'wpmail-dkim-sign' ),
				! empty( $result['skip_reason'] )
					? sprintf(
						/* translators: %s: skip reason from Signer. */
						esc_html__( 'Pre-flight skip reason: %s.', 'wpmail-dkim-sign' ),
						esc_html( (string) $result['skip_reason'] )
					)
					: esc_html__( 'No pre-flight skip reason was recorded; this is unexpected.', 'wpmail-dkim-sign' )
			);
		}

		if ( ! empty( $result['headers'] ) ) {
			printf( '<details class="wpdkm-details"><summary>%s</summary>', esc_html__( 'Full captured headers', 'wpmail-dkim-sign' ) );
			printf( '<pre class="wpdkm-headers">%s</pre>', esc_html( (string) $result['headers'] ) );
			printf( '</details>' );
		} else {
			// No headers captured — already covered by the verdict above.
		}

		printf( '</div>' );
	}

	printf( '</section>' );
} )( $data );
