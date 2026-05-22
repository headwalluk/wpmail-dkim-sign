<?php
/**
 * Tab navigation partial.
 *
 * Hash-based tabs per dev-notes/patterns/admin-tabs.md — JS reads
 * the URL hash on load and on hashchange, toggles panel visibility.
 *
 * Body is wrapped in an immediately-invoked closure so local
 * variables ($tabs, foreach vars) are function-scoped rather than
 * file-scope (PHPCS WordPress.NamingConventions.PrefixAllGlobals).
 *
 * @package WPMail_DKIM_Sign
 */

namespace WPMail_DKIM_Sign;

defined( 'ABSPATH' ) || die();

( function ( array $data ): void {
	$tabs = array(
		array(
			'slug'  => 'setup',
			'label' => __( 'Setup', 'wpmail-dkim-sign' ),
		),
		array(
			'slug'  => 'verify',
			'label' => __( 'Verify', 'wpmail-dkim-sign' ),
		),
	);

	unset( $data );

	printf( '<nav class="nav-tab-wrapper wp-clearfix">' );

	foreach ( $tabs as $index => $tab ) {
		$active_class = ( 0 === $index ) ? ' nav-tab-active' : '';
		printf(
			'<a href="#%s" class="nav-tab%s" data-tab="%s">%s</a>',
			esc_attr( $tab['slug'] ),
			esc_attr( $active_class ),
			esc_attr( $tab['slug'] ),
			esc_html( $tab['label'] )
		);
	}

	printf( '</nav>' );
} )( $data );
