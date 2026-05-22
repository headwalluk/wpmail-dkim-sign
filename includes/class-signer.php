<?php
/**
 * Signer sub-service.
 *
 * Hooks phpmailer_init to set the DKIM_* properties on PHPMailer
 * before the message is sent, so PHPMailer's preSend() emits a
 * DKIM-Signature header.
 *
 * @package WPMail_DKIM_Sign
 */

namespace WPMail_DKIM_Sign;

defined( 'ABSPATH' ) || die();

/**
 * Signer
 *
 * Skeleton populated in M5 (pre-flight checks + DKIM_* property
 * assignment).
 *
 * @since 0.1.0
 */
class Signer {

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
		add_action( 'phpmailer_init', array( $this, 'sign' ), SIGNER_PRIORITY, 1 );
	}

	/**
	 * Apply a DKIM signature configuration to the PHPMailer instance.
	 *
	 * Placeholder in M1 — full pre-flight checks and DKIM_* property
	 * assignment land in M5. Until then this is a no-op, so even
	 * with the hook registered the plugin remains silent on the
	 * outbound mail path.
	 *
	 * @since 0.1.0
	 *
	 * @param \PHPMailer\PHPMailer\PHPMailer $phpmailer PHPMailer instance, passed by reference by WP core.
	 *
	 * @return void
	 */
	public function sign( \PHPMailer\PHPMailer\PHPMailer $phpmailer ): void {
		unset( $phpmailer ); // Placeholder — populated in M5.
	}
}
