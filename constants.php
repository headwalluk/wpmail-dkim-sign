<?php
/**
 * Plugin-wide constants.
 *
 * All magic strings and numbers used by the plugin live here. Defaults
 * are prefixed DEF_, option-row keys OPT_, capabilities CAP_,
 * admin-post action names ACTION_, nonce names NONCE_.
 *
 * Note: anything that depends on the OpenSSL extension (the key
 * type — OPENSSL_KEYTYPE_RSA) is referenced inline inside the
 * function that uses it (Keypair::generate), rather than as a
 * constant here, so that constants.php remains safe to load even
 * if ext-openssl is absent at bootstrap.
 *
 * @package WPMail_DKIM_Sign
 */

namespace WPMail_DKIM_Sign;

defined( 'ABSPATH' ) || die();

/* ---------- Versioning + filesystem ---------- */

// Plugin version and filesystem pointers live in the entry-point file as root-namespace
// WPDKM_* defines (see wpmail-dkim-sign.php), so uninstall.php and any other code that
// runs outside this namespace can reach them without bootstrapping the namespace.

/* ---------- Capabilities ---------- */

const CAP_MANAGE = 'manage_options';

/* ---------- Cryptography ---------- */

const KEY_BITS = 2048;

/* ---------- Formatting ---------- */

const DATE_FORMAT = 'Y-m-d H:i:s T';

/* ---------- Admin menu ---------- */

const MENU_SLUG = 'wpmail-dkim-sign';

/* ---------- wp_options row keys ---------- */

const OPT_SETTINGS = 'wpdkm_settings';
const OPT_KEYPAIR  = 'wpdkm_keypair';

/* ---------- Settings field defaults ---------- */

const DEF_SELECTOR         = 'website';
const DEF_CANONICALISATION = 'relaxed/relaxed';
const DEF_ENABLED          = false;

const ALLOWED_CANONICALISATIONS = array( 'simple/simple', 'relaxed/simple', 'relaxed/relaxed' );

/* ---------- admin-post.php action names ---------- */

const ACTION_SAVE           = 'wpdkm_save';
const ACTION_GENERATE_KEY   = 'wpdkm_generate_key';
const ACTION_REGENERATE_KEY = 'wpdkm_regenerate_key';
const ACTION_DELETE_KEY     = 'wpdkm_delete_key';
const ACTION_TEST_EMAIL     = 'wpdkm_test_email';
const ACTION_CHECK_DNS      = 'wpdkm_check_dns';

/* ---------- Nonce names ---------- */

const NONCE_SAVE           = 'wpdkm_save_nonce';
const NONCE_GENERATE_KEY   = 'wpdkm_generate_key_nonce';
const NONCE_REGENERATE_KEY = 'wpdkm_regenerate_key_nonce';
const NONCE_DELETE_KEY     = 'wpdkm_delete_key_nonce';
const NONCE_TEST_EMAIL     = 'wpdkm_test_email_nonce';
const NONCE_CHECK_DNS      = 'wpdkm_check_dns_nonce';

/* ---------- Admin notice query-string values ---------- */

const NOTICE_QUERY_PARAM            = 'wpdkm_notice';
const NOTICE_SAVED                  = 'saved';
const NOTICE_INVALID                = 'invalid';
const NOTICE_KEY_GENERATED          = 'key-generated';
const NOTICE_KEY_REPLACED           = 'key-replaced';
const NOTICE_KEY_DELETED            = 'key-deleted';
const NOTICE_KEY_ERROR              = 'key-error';
const NOTICE_TEST_SENT              = 'test-sent';
const NOTICE_TEST_FAILED            = 'test-failed';
const NOTICE_TEST_INVALID_RECIPIENT = 'test-invalid-recipient';
const NOTICE_DNS_CHECKED            = 'dns-checked';

/* ---------- Transient key prefixes + TTLs ---------- */

const TRANSIENT_CAPTURE_PREFIX   = 'wpdkm_capture_';
const TRANSIENT_DNS_CHECK_PREFIX = 'wpdkm_dns_check_';
const VERIFY_RESULT_TTL          = 30 * MINUTE_IN_SECONDS;

/* ---------- Hook priorities ---------- */

const SIGNER_PRIORITY  = 10;
const CAPTURE_PRIORITY = 999;

/* ---------- GitHub auto-updater ---------- */

const UPDATER_GITHUB_REPO = 'headwalluk/wpmail-dkim-sign';
const UPDATER_CACHE_KEY   = 'wpdkm_github_release';
const UPDATER_CACHE_TTL   = 12 * HOUR_IN_SECONDS;
