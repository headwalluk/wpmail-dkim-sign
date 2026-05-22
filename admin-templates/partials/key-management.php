<?php
/**
 * Key management partial.
 *
 * Renders the key-status block plus the generate / regenerate /
 * delete form buttons. Each button posts to admin-post.php with its
 * own action + nonce; regenerate and delete carry a data-attribute
 * picked up by admin.js to show a JS confirm() before submission.
 *
 * @package WPMail_DKIM_Sign
 */

namespace WPMail_DKIM_Sign;

defined( 'ABSPATH' ) || die();

printf( '<section class="wpdkm-section">' );
printf( '<h2>%s</h2>', esc_html__( 'Key pair', 'wpmail-dkim-sign' ) );

if ( is_null( $data['keypair_meta'] ) ) {
	printf(
		'<p>%s</p>',
		esc_html__( 'No key pair has been generated yet.', 'wpmail-dkim-sign' )
	);

	printf(
		'<form method="post" action="%s" class="wpdkm-inline-form">',
		esc_url( $data['admin_post'] )
	);
	printf(
		'<input type="hidden" name="action" value="%s" />',
		esc_attr( $data['actions']['generate_key'] )
	);
	wp_nonce_field( $data['actions']['generate_key'], $data['nonces']['generate_key'] );
	printf(
		'<button type="submit" class="button button-primary">%s</button>',
		esc_html__( 'Generate key pair', 'wpmail-dkim-sign' )
	);
	printf( '</form>' );
} else {
	printf( '<p class="wpdkm-key-status">' );
	printf(
		/* translators: %s: human-readable timestamp. */
		esc_html__( 'A 2048-bit RSA key pair was generated on %s.', 'wpmail-dkim-sign' ),
		esc_html( $data['keypair_meta']['created_at'] )
	);
	printf( '</p>' );

	printf( '<div class="wpdkm-button-row">' );

	/* Regenerate. */
	printf(
		'<form method="post" action="%s" class="wpdkm-inline-form">',
		esc_url( $data['admin_post'] )
	);
	printf(
		'<input type="hidden" name="action" value="%s" />',
		esc_attr( $data['actions']['regenerate_key'] )
	);
	wp_nonce_field( $data['actions']['regenerate_key'], $data['nonces']['regenerate_key'] );
	printf(
		'<button type="submit" class="button" data-wpdkm-confirm="%s">%s</button>',
		esc_attr__( 'Regenerating the key invalidates the current DNS record. You will need to publish a new TXT record before signing resumes. Continue?', 'wpmail-dkim-sign' ),
		esc_html__( 'Regenerate key', 'wpmail-dkim-sign' )
	);
	printf( '</form>' );

	/* Delete. */
	printf(
		'<form method="post" action="%s" class="wpdkm-inline-form">',
		esc_url( $data['admin_post'] )
	);
	printf(
		'<input type="hidden" name="action" value="%s" />',
		esc_attr( $data['actions']['delete_key'] )
	);
	wp_nonce_field( $data['actions']['delete_key'], $data['nonces']['delete_key'] );
	printf(
		'<button type="submit" class="button button-link-delete" data-wpdkm-confirm="%s">%s</button>',
		esc_attr__( 'Deleting the key disables signing immediately. You can regenerate later, but the new key will not match the published DNS record. Continue?', 'wpmail-dkim-sign' ),
		esc_html__( 'Delete key', 'wpmail-dkim-sign' )
	);
	printf( '</form>' );

	printf( '</div>' );
}

printf( '</section>' );
