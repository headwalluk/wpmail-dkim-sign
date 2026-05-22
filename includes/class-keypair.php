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
 * Skeleton populated in M2 (generate / store / load / delete /
 * exists / get_public_dkim_value / format_txt_record).
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
	 * Request-scoped cache of the loaded key material.
	 *
	 * Populated on first load() call within a request and reused
	 * for the rest of that request — a WP request can send many
	 * emails (Woo order: customer + admin + back-in-stock + …) and
	 * re-querying wp_options + re-parsing the PEM per email is
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
}
