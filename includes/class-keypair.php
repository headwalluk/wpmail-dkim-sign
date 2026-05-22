<?php
/**
 * Keypair sub-service.
 *
 * Owns the RSA-2048 keypair lifecycle: generate, store, load, delete.
 * Storage is a single wp_options row with autoload disabled, since
 * the PEM material is ~3 KB and only needed during admin operations
 * and actual mail signing — not on every front-end request.
 *
 * Note: KEY_TYPE constant (OPENSSL_KEYTYPE_RSA) is intentionally not
 * declared as a class constant here. Class constants are evaluated
 * at class-load time; if ext-openssl is missing on the host, even
 * loading this file would fatal. Instead, OPENSSL_KEYTYPE_RSA is
 * referenced inline inside generate() — the only place that needs
 * it, and the only place where openssl absence is guaranteed to
 * fail anyway.
 *
 * @package WPMail_DKIM_Sign
 */

namespace WPMail_DKIM_Sign;

defined( 'ABSPATH' ) || die();

/**
 * Keypair
 *
 * Handles RSA-2048 keypair generation and persistence. The settings
 * page in M3 will wire admin buttons to call the public methods
 * here (generate, regenerate, delete). Until that lands, the class
 * is exercised programmatically.
 *
 * @since 0.1.0
 */
class Keypair {

	/**
	 * Key length in bits. Locked to 2048 for v1 (RFC 8301 minimum
	 * recommended, broad receiver support).
	 *
	 * @since 0.1.0
	 *
	 * @var int
	 */
	private const KEY_BITS = 2048;

	/**
	 * Date format used for stored timestamps.
	 *
	 * Human-readable per house style — easier troubleshooting than
	 * a Unix timestamp.
	 *
	 * @since 0.1.0
	 *
	 * @var string
	 */
	private const DATE_FORMAT = 'Y-m-d H:i:s T';

	/**
	 * Request-scoped cache of the loaded key material.
	 *
	 * Populated on the first load() call within a request and
	 * reused for the rest of that request — a WP request can send
	 * many emails (Woo order: customer + admin + back-in-stock + …)
	 * and re-querying wp_options + re-parsing the PEM per email is
	 * wasteful. Never persists across requests, so regenerate
	 * immediately takes effect on the next request.
	 *
	 * @since 0.1.0
	 *
	 * @var array{private_pem: string, public_b64: string, created_at: string}|null
	 */
	private static ?array $cache = null;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 */
	public function __construct() {
		// Intentionally empty. Methods are stateless aside from the static cache.
	}

	/**
	 * Generate a fresh RSA-2048 keypair and persist it.
	 *
	 * Any pre-existing key in wp_options is overwritten. Callers
	 * confirming this with the user (regenerate flow) own the UI
	 * confirmation; this method makes no decisions about consent.
	 *
	 * @since 0.1.0
	 *
	 * @return bool True on success, false on OpenSSL failure.
	 */
	public function generate(): bool {
		$result = false;

		$resource = openssl_pkey_new(
			array(
				'private_key_bits' => self::KEY_BITS,
				'private_key_type' => OPENSSL_KEYTYPE_RSA,
			)
		);

		if ( false === $resource ) {
			self::log_openssl_error( 'openssl_pkey_new failed' );
		} else {
			$private_pem = '';
			$exported    = openssl_pkey_export( $resource, $private_pem );

			if ( false === $exported ) {
				self::log_openssl_error( 'openssl_pkey_export failed' );
			} else {
				$details = openssl_pkey_get_details( $resource );

				if ( false === $details || empty( $details['key'] ) ) {
					self::log_openssl_error( 'openssl_pkey_get_details failed' );
				} else {
					$public_b64 = self::pem_to_base64( $details['key'] );
					$this->store( $private_pem, $public_b64 );
					$result = true;
				}
			}
		}

		return $result;
	}

	/**
	 * Persist the keypair to wp_options.
	 *
	 * Stored as a single row with autoload disabled. Note: the
	 * third argument to update_option only influences the autoload
	 * flag when the option is being *added* for the first time; on
	 * subsequent updates the flag is preserved. Since we never
	 * stored this option with autoload enabled in any prior plugin
	 * version, the disabled flag set on first generate() carries
	 * through.
	 *
	 * @since 0.1.0
	 *
	 * @param string $private_pem PEM-encoded private key.
	 * @param string $public_b64  Raw base64 public key (no PEM markers).
	 *
	 * @return void
	 */
	private function store( string $private_pem, string $public_b64 ): void {
		$data = array(
			'private_pem' => $private_pem,
			'public_b64'  => $public_b64,
			'created_at'  => self::now_formatted(),
		);

		update_option( OPT_KEYPAIR, $data, false );
		self::$cache = $data;
	}

	/**
	 * Load the stored keypair from wp_options.
	 *
	 * Returns the cached value if already loaded this request. If
	 * the stored data is malformed or the private PEM no longer
	 * parses (corrupted at rest, openssl removed, etc.), returns
	 * null and logs the failure.
	 *
	 * @since 0.1.0
	 *
	 * @return array{private_pem: string, public_b64: string, created_at: string}|null
	 */
	public function load(): ?array {
		if ( ! is_null( self::$cache ) ) {
			// Use the cached value populated by an earlier call in this request.
		} else {
			$data = get_option( OPT_KEYPAIR, null );

			if ( ! is_array( $data )
				|| ! isset( $data['private_pem'], $data['public_b64'], $data['created_at'] ) ) {
				// No stored key, or stored data is malformed — treat as absent.
				self::$cache = null;
			} else {
				$parsed = openssl_pkey_get_private( $data['private_pem'] );

				if ( false === $parsed ) {
					self::log_openssl_error( 'stored private key failed to parse' );
					self::$cache = null;
				} else {
					self::$cache = $data;
				}
			}
		}

		return self::$cache;
	}

	/**
	 * Check whether a usable key is currently stored.
	 *
	 * @since 0.1.0
	 *
	 * @return bool
	 */
	public function exists(): bool {
		return ! is_null( $this->load() );
	}

	/**
	 * Delete the stored keypair and clear the request-scoped cache.
	 *
	 * No-op (apart from cache clear) if no key was stored.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function delete(): void {
		delete_option( OPT_KEYPAIR );
		self::$cache = null;
	}

	/**
	 * Return the raw base64 public-key value for the DKIM `p=` field.
	 *
	 * @since 0.1.0
	 *
	 * @return string|null Base64-encoded public key without PEM markers, or null if no key is stored.
	 */
	public function get_public_dkim_value(): ?string {
		$loaded = $this->load();
		$result = null;

		if ( is_null( $loaded ) ) {
			// No key stored — caller should treat as "key not yet generated".
		} else {
			$result = $loaded['public_b64'];
		}

		return $result;
	}

	/**
	 * Return the PEM-encoded private key for handing to PHPMailer.
	 *
	 * @since 0.1.0
	 *
	 * @return string|null PEM-encoded private key, or null if no key is stored.
	 */
	public function get_private_pem(): ?string {
		$loaded = $this->load();
		$result = null;

		if ( is_null( $loaded ) ) {
			// No key stored.
		} else {
			$result = $loaded['private_pem'];
		}

		return $result;
	}

	/**
	 * Build the DKIM DNS TXT-record fields for display in the admin UI.
	 *
	 * Returns the record name, the full single-string value, and the
	 * chunked form (quoted 255-character substrings joined by spaces)
	 * required by some DNS providers. Returns null if no key is
	 * stored yet.
	 *
	 * @since 0.1.0
	 *
	 * @param string $selector DKIM selector (DNS-label).
	 * @param string $domain   Signing domain.
	 *
	 * @return array{name: string, value_full: string, value_chunked: string}|null
	 */
	public function format_txt_record( string $selector, string $domain ): ?array {
		$public_b64 = $this->get_public_dkim_value();
		$result     = null;

		if ( is_null( $public_b64 ) ) {
			// No key stored yet — caller shows the "generate first" prompt instead.
		} else {
			$value_full = 'v=DKIM1; k=rsa; p=' . $public_b64;

			$result = array(
				'name'          => $selector . '._domainkey.' . $domain,
				'value_full'    => $value_full,
				'value_chunked' => self::chunk_quoted_strings( $value_full, 255 ),
			);
		}

		return $result;
	}

	/**
	 * Strip PEM headers and whitespace from a PEM-encoded public key.
	 *
	 * @since 0.1.0
	 *
	 * @param string $pem PEM-encoded key material.
	 *
	 * @return string Raw base64 with no headers or newlines.
	 */
	private static function pem_to_base64( string $pem ): string {
		return (string) preg_replace( '/-----[A-Z ]+-----|\s+/', '', $pem );
	}

	/**
	 * Split a long string into quoted N-character substrings joined by spaces.
	 *
	 * The form some DNS providers require when entering a single
	 * logical TXT record whose value exceeds the 255-byte
	 * single-string limit imposed by RFC 1035.
	 *
	 * @since 0.1.0
	 *
	 * @param string $value      Full record value.
	 * @param int    $chunk_size Maximum length of each quoted chunk.
	 *
	 * @return string
	 */
	private static function chunk_quoted_strings( string $value, int $chunk_size ): string {
		$chunks = str_split( $value, $chunk_size );
		$quoted = array();

		foreach ( $chunks as $chunk ) {
			$quoted[] = '"' . $chunk . '"';
		}

		return implode( ' ', $quoted );
	}

	/**
	 * Return the current time as a human-readable string in the
	 * site's configured timezone.
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	private static function now_formatted(): string {
		$now = new \DateTime( 'now', wp_timezone() );
		return $now->format( self::DATE_FORMAT );
	}

	/**
	 * Send an OpenSSL failure message to the PHP error log.
	 *
	 * Hard failures (key generation, key parsing) are logged
	 * unconditionally so site owners can find them without enabling
	 * WP_DEBUG_LOG specifically. Skip-decisions in Signer (M5) will
	 * use a debug-only logger separately.
	 *
	 * @since 0.1.0
	 *
	 * @param string $context Short description of where the failure occurred.
	 *
	 * @return void
	 */
	private static function log_openssl_error( string $context ): void {
		$detail  = (string) openssl_error_string();
		$message = sprintf( 'wpmail-dkim-sign: %s — %s', $context, $detail );

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Hard-failure log for OpenSSL operations.
		error_log( $message );
	}
}
