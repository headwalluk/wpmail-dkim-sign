<?php
/**
 * Verify-tab: Check DNS Record partial.
 *
 * Renders the Check DNS Record button and, when a recent check
 * exists in the transient, the structured result (match /
 * mismatch / nxdomain / parse_error / no_local_key).
 *
 * Body wrapped in an immediately-invoked closure to scope locals.
 *
 * @package WPMail_DKIM_Sign
 */

namespace WPMail_DKIM_Sign;

defined( 'ABSPATH' ) || die();

( function ( array $data ): void {
	$result = $data['dns_check_result'];

	$status_text = array(
		'match'                   => array(
			'kind'  => 'good',
			'label' => __( 'Match', 'wpmail-dkim-sign' ),
			'body'  => __( 'The published p= value matches the locally-stored public key. DKIM verification at receivers should succeed (assuming the message body is not modified in transit).', 'wpmail-dkim-sign' ),
		),
		'mismatch'                => array(
			'kind'  => 'bad',
			'label' => __( 'Mismatch', 'wpmail-dkim-sign' ),
			'body'  => __( 'A DKIM record is published, but its p= value differs from the locally-stored public key. This typically means a key was regenerated locally without republishing the new DNS record, or the wrong record was published.', 'wpmail-dkim-sign' ),
		),
		'nxdomain'                => array(
			'kind'  => 'bad',
			'label' => __( 'No record found', 'wpmail-dkim-sign' ),
			'body'  => __( 'DNS reports no TXT record at this name. Either the record has not been published yet, propagation has not completed, or the wrong name was used.', 'wpmail-dkim-sign' ),
		),
		'parse_error'             => array(
			'kind'  => 'bad',
			'label' => __( 'Record present but unparseable', 'wpmail-dkim-sign' ),
			'body'  => __( 'TXT records were returned at this name but none contained a valid v=DKIM1 / p= structure. Check the DNS provider for a malformed entry.', 'wpmail-dkim-sign' ),
		),
		'no_local_key'            => array(
			'kind'  => 'bad',
			'label' => __( 'No local key', 'wpmail-dkim-sign' ),
			'body'  => __( 'There is no key stored locally to compare against. Generate a key pair first.', 'wpmail-dkim-sign' ),
		),
		'doubled_zone'            => array(
			'kind'  => 'bad',
			'label' => __( 'Misconfigured DNS — zone appended twice', 'wpmail-dkim-sign' ),
			'body'  => __( 'Your DNS provider has duplicated the zone in the record name. This usually happens when the full FQDN is pasted into the provider\'s "Host" or "Name" field, which then auto-appends the zone. Edit the Host field to omit the zone part — use just the "Host name (recommended)" value from the Setup tab.', 'wpmail-dkim-sign' ),
		),
		'missing_domainkey_label' => array(
			'kind'  => 'bad',
			'label' => __( 'Misconfigured DNS — missing _domainkey label', 'wpmail-dkim-sign' ),
			'body'  => __( 'A TXT record exists, but the "_domainkey" subdomain is missing from the name. The DKIM record name must include "._domainkey." between the selector and the signing domain. Compare your DNS entry to the "Host name (recommended)" value on the Setup tab.', 'wpmail-dkim-sign' ),
		),
	);

	printf( '<section class="wpdkm-section">' );
	printf( '<h2>%s</h2>', esc_html__( 'Check DNS record', 'wpmail-dkim-sign' ) );
	printf(
		'<p>%s</p>',
		esc_html__( 'Performs a fresh DNS lookup of <selector>._domainkey.<signing-domain> and compares the published p= value to the locally-stored public key. The result is not cached — propagation issues are the most common DNS gotcha and a fresh lookup is cheap.', 'wpmail-dkim-sign' )
	);

	printf( '<form method="post" action="%s" class="wpdkm-form">', esc_url( $data['admin_post'] ) );
	printf( '<input type="hidden" name="action" value="%s" />', esc_attr( $data['actions']['check_dns'] ) );
	wp_nonce_field( $data['actions']['check_dns'], $data['nonces']['check_dns'] );
	printf(
		'<p><button type="submit" class="button button-primary">%s</button></p>',
		esc_html__( 'Check DNS record', 'wpmail-dkim-sign' )
	);
	printf( '</form>' );

	if ( is_null( $result ) ) {
		// No recent check — result panel hidden.
	} else {
		$status = (string) ( $result['status'] ?? '' );
		$entry  = $status_text[ $status ] ?? array(
			'kind'  => 'bad',
			'label' => __( 'Unknown status', 'wpmail-dkim-sign' ),
			'body'  => '',
		);

		printf( '<div class="wpdkm-result-panel">' );
		printf( '<h3>%s</h3>', esc_html__( 'Last DNS check result', 'wpmail-dkim-sign' ) );

		printf( '<ul class="wpdkm-result-meta">' );
		printf(
			'<li><strong>%s:</strong> %s</li>',
			esc_html__( 'Checked at', 'wpmail-dkim-sign' ),
			esc_html( (string) ( $result['checked_at'] ?? '' ) )
		);
		printf(
			'<li><strong>%s:</strong> <code>%s</code></li>',
			esc_html__( 'Record name', 'wpmail-dkim-sign' ),
			esc_html( (string) ( $result['name'] ?? '' ) )
		);
		printf( '</ul>' );

		printf(
			'<p class="wpdkm-verdict wpdkm-verdict-%s"><strong>%s.</strong> %s</p>',
			esc_attr( $entry['kind'] ),
			esc_html( $entry['label'] ),
			esc_html( $entry['body'] )
		);

		if ( ! empty( $result['detail'] ) ) {
			printf( '<p class="description">%s</p>', esc_html( (string) $result['detail'] ) );
		} else {
			// No extra detail line for this result.
		}

		if ( ! empty( $result['misconfig_name'] ) ) {
			printf(
				'<p><strong>%s</strong> <code>%s</code></p>',
				esc_html__( 'Record actually found at:', 'wpmail-dkim-sign' ),
				esc_html( (string) $result['misconfig_name'] )
			);
		} else {
			// Not a misconfig status — no actually-found-at line.
		}

		if ( 'mismatch' === $status && ! empty( $result['published_b64'] ) ) {
			printf( '<details class="wpdkm-details"><summary>%s</summary>', esc_html__( 'Show published vs. local key', 'wpmail-dkim-sign' ) );
			printf( '<p><strong>%s:</strong></p><pre class="wpdkm-headers">%s</pre>', esc_html__( 'Published p=', 'wpmail-dkim-sign' ), esc_html( (string) $result['published_b64'] ) );
			$local_b64 = (string) get_plugin()->get_keypair()->get_public_dkim_value();
			printf( '<p><strong>%s:</strong></p><pre class="wpdkm-headers">%s</pre>', esc_html__( 'Local key', 'wpmail-dkim-sign' ), esc_html( $local_b64 ) );
			printf( '</details>' );
		} else {
			// No mismatch detail to show.
		}

		printf( '</div>' );
	}

	printf( '</section>' );
} )( $data );
