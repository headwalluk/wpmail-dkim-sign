<?php
/**
 * Verifier sub-service.
 *
 * Owns the diagnostic admin actions: send a test email (and capture
 * the resulting prepared headers for inspection) and check the
 * public DNS TXT record against the locally-stored public key.
 *
 * @package WPMail_DKIM_Sign
 */

namespace WPMail_DKIM_Sign;

defined( 'ABSPATH' ) || die();

/**
 * Verifier
 *
 * Skeleton populated in M6 (test-email + DNS-check admin-post
 * handlers, one-shot phpmailer_init capture filter).
 *
 * @since 0.1.0
 */
class Verifier {

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 */
	public function __construct() {
		// Intentionally empty. See register_hooks().
	}

	/**
	 * Register WordPress hooks owned by this sub-service.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		// Placeholder — admin_post_wpdkm_test_email and admin_post_wpdkm_check_dns added in M6.
	}
}
