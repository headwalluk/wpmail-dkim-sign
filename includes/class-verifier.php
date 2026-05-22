<?php
/**
 * Verifier sub-service.
 *
 * Owns the diagnostic admin actions: send a test email (capturing
 * the prepared headers, post-signing, so the user can confirm a
 * DKIM-Signature was attached) and check the public DNS TXT
 * record against the locally-stored public key.
 *
 * @package WPMail_DKIM_Sign
 */

namespace WPMail_DKIM_Sign;

use PHPMailer\PHPMailer\PHPMailer;

defined( 'ABSPATH' ) || die();

/**
 * Verifier
 *
 * Test-email and DNS-check handlers. Results are stored in
 * per-user transients and read back by Settings::build_page_data
 * for rendering inside the Verify tab.
 *
 * @since 0.1.0
 */
class Verifier {

	/**
	 * Per-request flag set by handle_test_email before triggering
	 * wp_mail, checked by capture_headers, and cleared as soon as
	 * the one-shot capture completes.
	 *
	 * Using a class-static rather than a $GLOBALS entry keeps the
	 * flag's lifetime explicit and within this class's surface area.
	 *
	 * @since 0.1.0
	 *
	 * @var bool
	 */
	private static bool $capturing = false;

	/**
	 * How long captured results stay in their transients before WP
	 * sweeps them. 30 minutes is long enough for an admin to walk
	 * away, troubleshoot a DNS provider issue, and come back to
	 * compare results — short enough that stale data doesn't linger.
	 *
	 * @since 0.1.0
	 *
	 * @var int
	 */
	private const RESULT_TTL_SECONDS = 30 * MINUTE_IN_SECONDS;

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
		add_action( 'admin_post_' . ACTION_TEST_EMAIL, array( $this, 'handle_test_email' ) );
		add_action( 'admin_post_' . ACTION_CHECK_DNS, array( $this, 'handle_check_dns' ) );
	}

	/**
	 * Handle a Send Test Email form submission.
	 *
	 * Sends a short message via wp_mail using an explicit From: header
	 * that matches the configured signing domain (so the test exercises
	 * the plugin's intended sign-everything path, not whatever From:
	 * other plugins might have rewritten). Captures the prepared
	 * headers post-signing into a transient for display.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function handle_test_email(): void {
		check_admin_referer( ACTION_TEST_EMAIL, NONCE_TEST_EMAIL );
		$this->require_manage_capability();

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified above.
		$recipient_raw = isset( $_POST['wpdkm_recipient'] )
			? sanitize_text_field( wp_unslash( $_POST['wpdkm_recipient'] ) )
			: '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$recipient = sanitize_email( $recipient_raw );

		if ( '' === $recipient || ! is_email( $recipient ) ) {
			$this->redirect_with_notice( NOTICE_TEST_INVALID_RECIPIENT );
		} else {
			$this->run_test_send( $recipient );
		}
	}

	/**
	 * Actually drive the test send and capture the result.
	 *
	 * Split out from handle_test_email for readability — the side
	 * effects are intentionally linear: arm the capture flag, hook
	 * the capture filter, run wp_mail, tear down, persist, redirect.
	 *
	 * @since 0.1.0
	 *
	 * @param string $recipient Validated recipient email address.
	 *
	 * @return void
	 */
	private function run_test_send( string $recipient ): void {
		$domain = (string) get_plugin()->get_settings()->get( 'domain' );

		$subject = sprintf(
			/* translators: %s: site name. */
			__( '[WP Mail DKIM] Test from %s', 'wpmail-dkim-sign' ),
			(string) get_bloginfo( 'name' )
		);

		$body = sprintf(
			/* translators: %s: site URL. */
			__( "This is a test email from the WP Mail DKIM Sign plugin on %s.\n\nIf this message arrived, the basic delivery path is working. Inspect the Authentication-Results header at the receiving mailbox to confirm dkim=pass.", 'wpmail-dkim-sign' ),
			(string) home_url()
		);

		$headers = array(
			sprintf( 'From: WP Mail DKIM Test <wpdkm-test@%s>', $domain ),
		);

		self::$capturing = true;
		add_action( 'phpmailer_init', array( $this, 'capture_headers' ), CAPTURE_PRIORITY, 1 );

		$wp_mail_ok = wp_mail( $recipient, $subject, $body, $headers );

		remove_action( 'phpmailer_init', array( $this, 'capture_headers' ), CAPTURE_PRIORITY );
		self::$capturing = false;

		$captured = get_transient( self::test_transient_key() );

		if ( ! is_array( $captured ) ) {
			// capture_headers never fired (pre_wp_mail short-circuit, etc.) — record a minimal stub.
			$captured = array(
				'headers'        => '',
				'dkim_signature' => null,
				'skip_reason'    => 'phpmailer_init did not fire (another plugin short-circuited wp_mail)',
			);
		} else {
			// Use the captured payload as recorded by capture_headers().
		}

		$captured['sent_at']          = self::now_formatted();
		$captured['recipient']        = $recipient;
		$captured['wp_mail_returned'] = (bool) $wp_mail_ok;

		set_transient( self::test_transient_key(), $captured, self::RESULT_TTL_SECONDS );

		$this->redirect_with_notice(
			$wp_mail_ok ? NOTICE_TEST_SENT : NOTICE_TEST_FAILED,
			'verify'
		);
	}

	/**
	 * One-shot phpmailer_init filter that captures the prepared
	 * message after Signer has run.
	 *
	 * Runs at CAPTURE_PRIORITY (999) so it fires after Signer's
	 * priority-10 hook. preSend() is called to force PHPMailer to
	 * apply the DKIM configuration and build the final headers —
	 * we then read those headers via getSentMIMEMessage().
	 *
	 * @since 0.1.0
	 *
	 * @param PHPMailer $phpmailer PHPMailer instance.
	 *
	 * @return void
	 */
	public function capture_headers( PHPMailer $phpmailer ): void {
		if ( ! self::$capturing ) {
			// Not in a test send — ignore. Defense in depth against accidental fires.
		} else {
			self::$capturing = false;

			$headers_block = '';
			$mime          = '';

			try {
				$phpmailer->preSend();
				$mime = (string) $phpmailer->getSentMIMEMessage();
			} catch ( \Throwable $e ) {
				// preSend can throw if the message is malformed; we still want a record.
				$headers_block = sprintf( 'preSend exception: %s', $e->getMessage() );
			}

			if ( '' === $headers_block && '' !== $mime ) {
				$blank_line_at = strpos( $mime, "\r\n\r\n" );
				$headers_block = false === $blank_line_at ? $mime : substr( $mime, 0, $blank_line_at );
			} else {
				// Either preSend threw (headers_block populated above) or there's no MIME message.
			}

			$dkim_signature = self::extract_dkim_signature( $headers_block );
			$skip_reason    = Signer::get_last_skip_reason();

			set_transient(
				self::test_transient_key(),
				array(
					'headers'        => $headers_block,
					'dkim_signature' => $dkim_signature,
					'skip_reason'    => $skip_reason,
				),
				self::RESULT_TTL_SECONDS
			);
		}
	}

	/**
	 * Handle a Check DNS Record form submission.
	 *
	 * Performs a fresh DNS lookup (deliberately uncached — DNS
	 * propagation is the most common troubleshooting question and
	 * a fresh dns_get_record call is cheap) and compares the
	 * published p= value to the locally stored public key.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function handle_check_dns(): void {
		check_admin_referer( ACTION_CHECK_DNS, NONCE_CHECK_DNS );
		$this->require_manage_capability();

		$settings = get_plugin()->get_settings();
		$selector = (string) $settings->get( 'selector' );
		$domain   = (string) $settings->get( 'domain' );
		$zone     = compute_zone( $domain );
		$name     = $selector . '._domainkey.' . $domain;

		$local_b64            = get_plugin()->get_keypair()->get_public_dkim_value();
		$result               = self::lookup_and_compare( $name, $local_b64, $zone, $selector, $domain );
		$result['checked_at'] = self::now_formatted();
		$result['name']       = $name;

		set_transient( self::dns_transient_key(), $result, self::RESULT_TTL_SECONDS );

		$this->redirect_with_notice( NOTICE_DNS_CHECKED, 'verify' );
	}

	/**
	 * Perform the DNS lookup and produce a structured result.
	 *
	 * On NXDOMAIN at the intended name, probes a small set of
	 * common misconfiguration patterns (doubled-zone, missing
	 * _domainkey label) and reports those as distinct statuses
	 * with corrective guidance.
	 *
	 * Possible outcomes (status field):
	 * - "match"                    — record present at the right name, p= matches.
	 * - "mismatch"                 — record present at the right name, p= differs.
	 * - "nxdomain"                 — no record at the right name and no known misconfig variants found.
	 * - "parse_error"              — record present but no parseable p= value.
	 * - "no_local_key"             — nothing stored locally to compare against.
	 * - "doubled_zone"             — record found at <intended>.<zone> (zone appended twice).
	 * - "missing_domainkey_label"  — record found at <selector>.<domain> (without ._domainkey.).
	 *
	 * @since 0.1.0
	 *
	 * @param string      $name      DNS name to query (the intended FQDN).
	 * @param string|null $local_b64 Locally stored public-key base64, or null if no key.
	 * @param string|null $zone      Detected authoritative zone, or null.
	 * @param string      $selector  Configured DKIM selector.
	 * @param string      $domain    Configured signing domain.
	 *
	 * @return array{status: string, published_b64: string|null, detail: string|null, misconfig_name?: string}
	 */
	private static function lookup_and_compare( string $name, ?string $local_b64, ?string $zone, string $selector, string $domain ): array {
		$result = array(
			'status'        => 'nxdomain',
			'published_b64' => null,
			'detail'        => null,
		);

		if ( is_null( $local_b64 ) ) {
			$result['status'] = 'no_local_key';
		} else {
			$records = @dns_get_record( $name, DNS_TXT ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- dns_get_record warns on NXDOMAIN; we handle the empty case below.

			if ( is_array( $records ) && ! empty( $records ) ) {
				$published = self::find_dkim_p_value( $records );

				if ( is_null( $published ) ) {
					$result['status'] = 'parse_error';
					$result['detail'] = 'no v=DKIM1 record with a parseable p= value at this name';
				} elseif ( $published === $local_b64 ) {
					$result['status']        = 'match';
					$result['published_b64'] = $published;
				} else {
					$result['status']        = 'mismatch';
					$result['published_b64'] = $published;
				}
			} else {
				$misconfig = self::probe_misconfigurations( $name, $zone, $selector, $domain );

				if ( ! is_null( $misconfig ) ) {
					$result = $misconfig;
				} else {
					// No misconfigured variants found either — genuine NXDOMAIN.
					$result['status'] = 'nxdomain';
				}
			}
		}

		return $result;
	}

	/**
	 * Probe for common DNS misconfigurations when the intended record is missing.
	 *
	 * Checks (in order):
	 * 1. Doubled zone: record at `<intended>.<zone>` (user pasted FQDN
	 *    into a Host field that auto-appends the zone).
	 * 2. Missing _domainkey label: record at `<selector>.<domain>` (user
	 *    typed only the selector in the Host field and dropped the
	 *    _domainkey subdomain).
	 *
	 * Returns the first match found, or null if no misconfig detected.
	 *
	 * @since 0.1.0
	 *
	 * @param string      $intended_name FQDN the record *should* live at.
	 * @param string|null $zone          Authoritative zone (or null if unknown — doubled-zone check is skipped).
	 * @param string      $selector      DKIM selector.
	 * @param string      $domain        Signing domain.
	 *
	 * @return array{status: string, published_b64: string|null, detail: string|null, misconfig_name: string}|null
	 */
	private static function probe_misconfigurations( string $intended_name, ?string $zone, string $selector, string $domain ): ?array {
		$result = null;

		if ( ! is_null( $zone ) ) {
			$doubled = $intended_name . '.' . $zone;
			$found   = self::find_dkim_p_at( $doubled );

			if ( ! is_null( $found ) ) {
				$result = array(
					'status'         => 'doubled_zone',
					'published_b64'  => $found,
					'detail'         => sprintf(
						'Your DNS provider has appended the zone "%s" automatically — the record landed at "%s" instead of "%s". Edit the Host field at your provider so it does NOT contain the zone portion.',
						$zone,
						$doubled,
						$intended_name
					),
					'misconfig_name' => $doubled,
				);
			} else {
				// Doubled-zone variant not present — fall through to next check.
			}
		} else {
			// Zone unknown — can't construct the doubled-zone candidate.
		}

		if ( is_null( $result ) ) {
			$missing_label = $selector . '.' . $domain;
			$found         = self::find_dkim_p_at( $missing_label );

			if ( ! is_null( $found ) ) {
				$result = array(
					'status'         => 'missing_domainkey_label',
					'published_b64'  => $found,
					'detail'         => sprintf(
						'A DKIM record exists at "%s" — but it is missing the "_domainkey" subdomain. The record name must include "._domainkey." between the selector and the signing domain.',
						$missing_label
					),
					'misconfig_name' => $missing_label,
				);
			} else {
				// Missing-label variant not present.
			}
		} else {
			// Earlier check already matched.
		}

		return $result;
	}

	/**
	 * Look up a name and return the first v=DKIM1 p= value found, or null.
	 *
	 * @since 0.1.0
	 *
	 * @param string $name DNS name to query.
	 *
	 * @return string|null
	 */
	private static function find_dkim_p_at( string $name ): ?string {
		$result  = null;
		$records = @dns_get_record( $name, DNS_TXT ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- dns_get_record warns on NXDOMAIN; we handle the empty case below.

		if ( is_array( $records ) && ! empty( $records ) ) {
			$result = self::find_dkim_p_value( $records );
		} else {
			// NXDOMAIN — no record at this name.
		}

		return $result;
	}

	/**
	 * Iterate DNS records and extract the first v=DKIM1 record's p= value.
	 *
	 * Multi-string TXT records are concatenated by dns_get_record into
	 * the 'txt' field, so we don't need to handle the chunked form
	 * separately here — receivers and resolvers join the pieces
	 * before delivering the record to us.
	 *
	 * @since 0.1.0
	 *
	 * @param array<int, array<string, mixed>> $records dns_get_record output.
	 *
	 * @return string|null Base64 public-key value, or null if no parseable DKIM1 record found.
	 */
	private static function find_dkim_p_value( array $records ): ?string {
		$result = null;

		foreach ( $records as $record ) {
			$txt = (string) ( $record['txt'] ?? '' );

			if ( '' === $txt || false === strpos( $txt, 'v=DKIM1' ) ) {
				// Not a DKIM record — keep looking.
			} elseif ( is_null( $result ) && preg_match( '/p=([A-Za-z0-9+\/=]+)/', $txt, $matches ) ) {
				$result = $matches[1];
			} else {
				// Either we already found one, or this DKIM record has no parseable p=.
			}
		}

		return $result;
	}

	/**
	 * Extract a DKIM-Signature header value from a raw header block, if present.
	 *
	 * Returns the unfolded header value (without the "DKIM-Signature:"
	 * prefix and without line-folding whitespace) for display.
	 *
	 * @since 0.1.0
	 *
	 * @param string $headers_block Raw header block (CRLF-separated).
	 *
	 * @return string|null
	 */
	private static function extract_dkim_signature( string $headers_block ): ?string {
		$result = null;

		if ( preg_match( '/^DKIM-Signature:\s*(.+?)(?=\r\n[A-Za-z!-9;-~]+:|\z)/sm', $headers_block, $matches ) ) {
			// Collapse header folding (CRLF + WSP → single space) for compact display.
			$result = trim( (string) preg_replace( '/\r\n\s+/', ' ', $matches[1] ) );
		} else {
			// No DKIM-Signature header present in the captured block.
		}

		return $result;
	}

	/**
	 * Public accessor: return the captured test-email payload, if any.
	 *
	 * Read by Settings::build_page_data when assembling the Verify-tab
	 * render data. Returns null if no test has been run recently.
	 *
	 * @since 0.1.0
	 *
	 * @return array<string, mixed>|null
	 */
	public function get_test_result(): ?array {
		$result = get_transient( self::test_transient_key() );
		return is_array( $result ) ? $result : null;
	}

	/**
	 * Public accessor: return the most recent DNS-check result, if any.
	 *
	 * @since 0.1.0
	 *
	 * @return array<string, mixed>|null
	 */
	public function get_dns_check_result(): ?array {
		$result = get_transient( self::dns_transient_key() );
		return is_array( $result ) ? $result : null;
	}

	/**
	 * Per-user transient key for the captured test-email payload.
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	private static function test_transient_key(): string {
		return TRANSIENT_CAPTURE_PREFIX . (string) get_current_user_id();
	}

	/**
	 * Per-user transient key for the DNS-check payload.
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	private static function dns_transient_key(): string {
		return TRANSIENT_DNS_CHECK_PREFIX . (string) get_current_user_id();
	}

	/**
	 * Capability check used by both admin-post handlers.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	private function require_manage_capability(): void {
		if ( current_user_can( CAP_MANAGE ) ) {
			// Authorised — continue.
		} else {
			wp_die(
				esc_html__( 'You do not have permission to perform this action.', 'wpmail-dkim-sign' ),
				'',
				array( 'response' => 403 )
			);
		}
	}

	/**
	 * Redirect back to the settings page with a notice query-arg and tab fragment.
	 *
	 * The tab fragment (#verify) is read by admin.js to switch the
	 * active tab on load, so the result appears on the same tab the
	 * user submitted from.
	 *
	 * @since 0.1.0
	 *
	 * @param string $notice Notice identifier.
	 * @param string $tab    Tab slug to activate after redirect.
	 *
	 * @return void
	 */
	private function redirect_with_notice( string $notice, string $tab = 'verify' ): void {
		$target = add_query_arg(
			array(
				'page'             => MENU_SLUG,
				NOTICE_QUERY_PARAM => $notice,
			),
			admin_url( 'options-general.php' )
		);

		wp_safe_redirect( $target . '#' . $tab );
		exit;
	}

	/**
	 * Current timestamp in the project's human-readable format.
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	private static function now_formatted(): string {
		$now = new \DateTime( 'now', wp_timezone() );
		return $now->format( 'Y-m-d H:i:s T' );
	}
}
