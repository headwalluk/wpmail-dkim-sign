<?php
/**
 * DNS TXT record display partial.
 *
 * Renders the host name (recommended — what most DNS provider UIs
 * want in the "Host" field), the full FQDN (fallback for providers
 * that ask for the complete name), and the record value in both
 * single-string and chunked-255 forms.
 *
 * When the authoritative zone for the signing domain can't be
 * detected (DNS lookup failure, weird setup) the host_name field
 * is null and only the full FQDN is shown.
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
	printf(
		'<tr><th scope="row">%s</th><td><code>TXT</code></td></tr>',
		esc_html__( 'Record type', 'wpmail-dkim-sign' )
	);
	printf( '</tbody></table>' );

	if ( ! is_null( $record['host_name'] ) ) {
		printf( '<h3 class="wpdkm-recommended-heading">%s</h3>', esc_html__( 'Host name (recommended)', 'wpmail-dkim-sign' ) );
		printf(
			'<p class="description">%s</p>',
			esc_html__( 'Most DNS provider UIs have a "Host" or "Name" field that automatically appends your zone. Paste just this part into that field.', 'wpmail-dkim-sign' )
		);
		printf( '<div class="wpdkm-copy-block widefat wpdkm-recommended">' );
		printf(
			'<textarea class="wpdkm-record-host code" readonly rows="1">%s</textarea>',
			esc_textarea( $record['host_name'] )
		);
		printf(
			'<button type="button" class="button button-primary wpdkm-copy-button" data-wpdkm-copy-target=".wpdkm-record-host">%s</button>',
			esc_html__( 'Copy host name', 'wpmail-dkim-sign' )
		);
		printf( '</div>' );

		printf( '<h3>%s</h3>', esc_html__( 'Full record name (FQDN)', 'wpmail-dkim-sign' ) );
		printf(
			'<p class="description">%s</p>',
			esc_html__( 'If your DNS provider asks for the fully-qualified record name instead, use this. Do not paste this into a "Host" field that auto-appends your zone — you will end up with the zone duplicated.', 'wpmail-dkim-sign' )
		);
	} else {
		printf( '<h3>%s</h3>', esc_html__( 'Record name', 'wpmail-dkim-sign' ) );
		printf(
			'<p class="description">%s</p>',
			esc_html__( "Couldn't auto-detect your DNS zone, so only the full record name is shown. Most providers accept this form.", 'wpmail-dkim-sign' )
		);
	}

	printf( '<div class="wpdkm-copy-block widefat">' );
	printf(
		'<textarea class="wpdkm-record-name code" readonly rows="1">%s</textarea>',
		esc_textarea( $record['name'] )
	);
	printf(
		'<button type="button" class="button wpdkm-copy-button" data-wpdkm-copy-target=".wpdkm-record-name">%s</button>',
		esc_html__( 'Copy full record name', 'wpmail-dkim-sign' )
	);
	printf( '</div>' );

	printf( '<h3>%s</h3>', esc_html__( 'Value (single string)', 'wpmail-dkim-sign' ) );
	printf(
		'<p class="description">%s</p>',
		esc_html__( 'Try this form first — most DNS providers handle the length automatically.', 'wpmail-dkim-sign' )
	);
	printf( '<div class="wpdkm-copy-block widefat">' );
	printf(
		'<textarea class="wpdkm-record-value code" readonly rows="4">%s</textarea>',
		esc_textarea( $record['value_full'] )
	);
	printf(
		'<button type="button" class="button wpdkm-copy-button" data-wpdkm-copy-target=".wpdkm-record-value">%s</button>',
		esc_html__( 'Copy', 'wpmail-dkim-sign' )
	);
	printf( '</div>' );

	printf( '<h3>%s</h3>', esc_html__( 'Value (chunked form)', 'wpmail-dkim-sign' ) );
	printf(
		'<p class="description">%s</p>',
		esc_html__( 'Use this form only if your DNS provider rejects the single-string value. The two forms encode the same record.', 'wpmail-dkim-sign' )
	);
	printf( '<div class="wpdkm-copy-block widefat">' );
	printf(
		'<textarea class="wpdkm-record-chunked code" readonly rows="4">%s</textarea>',
		esc_textarea( $record['value_chunked'] )
	);
	printf(
		'<button type="button" class="button wpdkm-copy-button" data-wpdkm-copy-target=".wpdkm-record-chunked">%s</button>',
		esc_html__( 'Copy', 'wpmail-dkim-sign' )
	);
	printf( '</div>' );

	printf( '</section>' );
} )( $data );
