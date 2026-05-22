<?php
/**
 * DNS TXT record display partial.
 *
 * Renders the record name plus the full single-string value and
 * the chunked quoted-strings form for older DNS providers that
 * cannot enter the full value as one string.
 *
 * Body wrapped in an immediately-invoked closure to scope the
 * $record local.
 *
 * @package WPMail_DKIM_Sign
 */

namespace WPMail_DKIM_Sign;

defined( 'ABSPATH' ) || die();

( function ( array $data ): void {
	$record = $data['dns_record'];

	printf( '<section class="wpdkm-section">' );
	printf( '<h2>%s</h2>', esc_html__( 'DNS TXT record', 'wpmail-dkim-sign' ) );
	printf(
		'<p>%s</p>',
		esc_html__( 'Publish the following TXT record at your DNS provider. Propagation typically takes a few minutes but can be up to 24 hours.', 'wpmail-dkim-sign' )
	);

	printf( '<table class="form-table" role="presentation"><tbody>' );
	printf( '<tr><th scope="row">%s</th><td><code>%s</code></td></tr>', esc_html__( 'Record name', 'wpmail-dkim-sign' ), esc_html( $record['name'] ) );
	printf( '<tr><th scope="row">%s</th><td><code>TXT</code></td></tr>', esc_html__( 'Record type', 'wpmail-dkim-sign' ) );
	printf( '</tbody></table>' );

	printf( '<h3>%s</h3>', esc_html__( 'Value (single string)', 'wpmail-dkim-sign' ) );
	printf( '<p class="description">%s</p>', esc_html__( 'Try this form first — most DNS providers handle the length automatically.', 'wpmail-dkim-sign' ) );
	printf( '<div class="wpdkm-copy-block widefat">' );
	printf( '<textarea class="wpdkm-record-value code" readonly rows="4">%s</textarea>', esc_textarea( $record['value_full'] ) );
	printf( '<button type="button" class="button wpdkm-copy-button" data-wpdkm-copy-target=".wpdkm-record-value">%s</button>', esc_html__( 'Copy', 'wpmail-dkim-sign' ) );
	printf( '</div>' );

	printf( '<h3>%s</h3>', esc_html__( 'Value (chunked form)', 'wpmail-dkim-sign' ) );
	printf(
		'<p class="description">%s</p>',
		esc_html__( 'Use this form only if your DNS provider rejects the single-string value. The two forms encode the same record.', 'wpmail-dkim-sign' )
	);
	printf( '<div class="wpdkm-copy-block widefat">' );
	printf( '<textarea class="wpdkm-record-chunked code" readonly rows="4">%s</textarea>', esc_textarea( $record['value_chunked'] ) );
	printf( '<button type="button" class="button wpdkm-copy-button" data-wpdkm-copy-target=".wpdkm-record-chunked">%s</button>', esc_html__( 'Copy', 'wpmail-dkim-sign' ) );
	printf( '</div>' );

	printf( '</section>' );
} )( $data );
