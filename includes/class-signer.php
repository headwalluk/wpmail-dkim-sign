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

use PHPMailer\PHPMailer\PHPMailer;

defined( 'ABSPATH' ) || die();

/**
 * Signer
 *
 * Decides whether and how to sign a given PHPMailer message, then
 * applies the DKIM_* configuration. The actual signing work is
 * performed by PHPMailer's preSend() once these properties are
 * set — we do not implement the DKIM algorithm ourselves.
 *
 * @since 1.0.0
 */
class Signer {

	/**
	 * Skip reason recorded by the most recent sign() invocation in
	 * this request, or null if the message was signed.
	 *
	 * Read by Verifier when assembling the test-email diagnostic so
	 * the user sees *why* a test send was left unsigned without
	 * having to enable WP_DEBUG_LOG.
	 *
	 * @since 1.0.0
	 *
	 * @var string|null
	 */
	private static ?string $last_skip_reason = null;

	/**
	 * Return the skip reason from the most recent sign() invocation, or null.
	 *
	 * @since 1.0.0
	 *
	 * @return string|null
	 */
	public static function get_last_skip_reason(): ?string {
		return self::$last_skip_reason;
	}

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		// Intentionally empty. See register_hooks().
	}

	/**
	 * Register WordPress hooks owned by this sub-service.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'phpmailer_init', array( $this, 'sign' ), SIGNER_PRIORITY, 1 );
	}

	/**
	 * Apply DKIM configuration to a PHPMailer instance, when all pre-flight checks pass.
	 *
	 * On any failed pre-flight check, the message is left untouched
	 * and the reason is logged at debug level (gated on WP_DEBUG).
	 *
	 * @since 1.0.0
	 *
	 * @param PHPMailer $phpmailer PHPMailer instance, passed by reference by WP core.
	 *
	 * @return void
	 */
	public function sign( PHPMailer $phpmailer ): void {
		self::$last_skip_reason = null;

		$skip_reason = self::should_skip( $phpmailer );

		if ( is_null( $skip_reason ) ) {
			$this->apply_dkim_config( $phpmailer );
		} else {
			self::$last_skip_reason = $skip_reason;
			self::log_skip( $skip_reason );
		}
	}

	/**
	 * Run all pre-flight checks in order. Returns null to proceed
	 * with signing, or a short string explaining why this message
	 * should be left unsigned.
	 *
	 * Checks run in cheapest-first order so that the common
	 * "plugin disabled" no-op exits without touching the database.
	 *
	 * @since 1.0.0
	 *
	 * @param PHPMailer $phpmailer PHPMailer instance.
	 *
	 * @return string|null
	 */
	private static function should_skip( PHPMailer $phpmailer ): ?string {
		$result = self::check_enabled();

		if ( is_null( $result ) ) {
			$result = self::check_key_present();
		} else {
			// Earlier check already failed; later checks are skipped.
		}

		if ( is_null( $result ) ) {
			$result = self::check_no_existing_dkim( $phpmailer );
		} else {
			// Earlier check already failed.
		}

		if ( is_null( $result ) ) {
			$result = self::check_from_matches( $phpmailer );
		} else {
			// Earlier check already failed.
		}

		return $result;
	}

	/**
	 * Pre-flight check 1: plugin is enabled in settings.
	 *
	 * @since 1.0.0
	 *
	 * @return string|null Null to proceed; skip-reason string otherwise.
	 */
	private static function check_enabled(): ?string {
		$enabled = (bool) filter_var(
			get_plugin()->get_settings()->get( 'enabled' ),
			FILTER_VALIDATE_BOOLEAN
		);

		return $enabled ? null : 'plugin disabled in settings';
	}

	/**
	 * Pre-flight check 2: a stored key exists and loads cleanly.
	 *
	 * @since 1.0.0
	 *
	 * @return string|null
	 */
	private static function check_key_present(): ?string {
		return is_null( get_plugin()->get_keypair()->load() )
			? 'no usable key stored'
			: null;
	}

	/**
	 * Pre-flight check 3: PHPMailer's DKIM_domain is not already set.
	 *
	 * If another plugin (e.g. WP Mail SMTP's DKIM feature) has
	 * already configured signing, we step aside — first-write wins,
	 * and overriding an existing signer is a fast path to two
	 * mis-aligned signatures.
	 *
	 * @since 1.0.0
	 *
	 * @param PHPMailer $phpmailer PHPMailer instance.
	 *
	 * @return string|null
	 */
	private static function check_no_existing_dkim( PHPMailer $phpmailer ): ?string {
		return empty( $phpmailer->DKIM_domain )
			? null
			: 'PHPMailer DKIM_domain already set — another plugin is signing';
	}

	/**
	 * Pre-flight check 4: From: header domain matches configured d=.
	 *
	 * A mismatch means signing would produce a misaligned d= and
	 * the message would fail DMARC alignment at the receiver —
	 * worse than leaving it unsigned.
	 *
	 * @since 1.0.0
	 *
	 * @param PHPMailer $phpmailer PHPMailer instance.
	 *
	 * @return string|null
	 */
	private static function check_from_matches( PHPMailer $phpmailer ): ?string {
		$from_domain = self::extract_from_domain( $phpmailer );
		$configured  = strtolower( (string) get_plugin()->get_settings()->get( 'domain' ) );

		$result = null;
		if ( is_null( $from_domain ) ) {
			$result = 'could not parse From: domain';
		} elseif ( $from_domain !== $configured ) {
			$result = sprintf(
				'From: domain %s does not match configured d=%s',
				$from_domain,
				$configured
			);
		} else {
			// From: matches configured signing domain.
		}

		return $result;
	}

	/**
	 * Parse the domain part of PHPMailer's From property.
	 *
	 * PHPMailer's From property is the bare email address (display
	 * names are tracked separately in FromName), so a simple
	 * split-on-@ suffices.
	 *
	 * @since 1.0.0
	 *
	 * @param PHPMailer $phpmailer PHPMailer instance.
	 *
	 * @return string|null Lowercased domain, or null if From is missing or unparseable.
	 */
	private static function extract_from_domain( PHPMailer $phpmailer ): ?string {
		$from   = trim( (string) ( $phpmailer->From ?? '' ) );
		$result = null;

		if ( '' === $from ) {
			// No From: set on the message.
		} else {
			$at = strrpos( $from, '@' );
			if ( false === $at ) {
				// Malformed address with no @ separator.
			} else {
				$result = strtolower( substr( $from, $at + 1 ) );
			}
		}

		return $result;
	}

	/**
	 * Apply the DKIM_* configuration to the PHPMailer instance.
	 *
	 * Assumes all pre-flight checks have passed — the key is loaded
	 * and the configured settings are present. Sets the private key
	 * via DKIM_private_string (the in-memory PEM variant) rather
	 * than DKIM_private (the file-path variant) so the key never
	 * touches disk.
	 *
	 * @since 1.0.0
	 *
	 * @param PHPMailer $phpmailer PHPMailer instance.
	 *
	 * @return void
	 */
	private function apply_dkim_config( PHPMailer $phpmailer ): void {
		$settings = get_plugin()->get_settings();
		$loaded   = get_plugin()->get_keypair()->load();
		$canon    = self::parse_canonicalisation( (string) $settings->get( 'canonicalisation' ) );

		$phpmailer->DKIM_domain                  = (string) $settings->get( 'domain' );
		$phpmailer->DKIM_selector                = (string) $settings->get( 'selector' );
		$phpmailer->DKIM_private_string          = $loaded['private_pem'];
		$phpmailer->DKIM_identity                = '';
		$phpmailer->DKIM_passphrase              = '';
		$phpmailer->DKIM_copyHeaderFields        = false;
		$phpmailer->DKIM_header_canonicalization = $canon['header'];
		$phpmailer->DKIM_body_canonicalization   = $canon['body'];
	}

	/**
	 * Split the "header/body" canonicalisation setting into its two parts.
	 *
	 * Settings values map to PHPMailer property values 1:1; PHPMailer
	 * uses the American spelling ("canonicalization") which we mirror
	 * only at the boundary.
	 *
	 * @since 1.0.0
	 *
	 * @param string $value Saved canonicalisation setting (e.g. "relaxed/relaxed").
	 *
	 * @return array{header: string, body: string}
	 */
	private static function parse_canonicalisation( string $value ): array {
		$parts = explode( '/', $value );

		return array(
			'header' => $parts[0] ?? 'relaxed',
			'body'   => $parts[1] ?? 'relaxed',
		);
	}

	/**
	 * Log a debug-level skip reason.
	 *
	 * Gated on WP_DEBUG so production sites do not accumulate log
	 * noise during normal operation. Hard failures (e.g. a stored
	 * key that fails to parse) are logged unconditionally by
	 * Keypair separately.
	 *
	 * @since 1.0.0
	 *
	 * @param string $reason Short skip-reason text.
	 *
	 * @return void
	 */
	private static function log_skip( string $reason ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug-only log gated on WP_DEBUG.
			error_log( 'wpmail-dkim-sign: skipped signing — ' . $reason );
		} else {
			// WP_DEBUG off — stay silent in production.
		}
	}
}
