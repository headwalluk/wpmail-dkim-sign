# Architecture

**Last Updated:** 2026-05-22

Concrete shape for the v1 build. Pairs with [`00-project-tracker.md`](00-project-tracker.md) (milestones) and [`01-decisions.md`](01-decisions.md) (why we picked this shape).

---

## File layout

```
wpmail-dkim-sign/
├── wpmail-dkim-sign.php          # Plugin header, ABSPATH guard, bootstrap
├── constants.php                  # Namespace + OPT_/DEF_/CAP_ constants
├── uninstall.php                  # Opt-in option removal
├── readme.txt                     # WP.org-format readme
├── LICENSE                        # GPLv2-or-later
├── phpcs.xml                      # WP Coding Standards config
├── .distignore                    # (exists) excludes dev artefacts from build
│
├── includes/
│   ├── class-plugin.php           # Top-level orchestrator (lazy-loads services, registers hooks)
│   ├── class-settings.php         # Admin page + form handling
│   ├── class-keypair.php          # Generate / store / load / delete RSA-2048 keypair
│   ├── class-signer.php           # phpmailer_init handler + pre-flight checks
│   └── class-verifier.php         # Test-email + DNS-check admin actions
│
├── admin-templates/
│   ├── settings-page.php          # Main settings page view (code-first, printf-driven)
│   └── partials/
│       ├── key-management.php     # Generate / regenerate / delete buttons + status
│       ├── dns-record.php         # TXT record name + value + chunked form
│       └── verification.php       # Test-email form + DNS-check button + result panels
│
├── assets/
│   └── admin/
│       ├── admin.css              # Settings page styles
│       └── admin.js               # Confirm prompts, copy-to-clipboard, AJAX (if any)
│
├── languages/                     # (empty for v1, ready for translators)
│
└── dev-notes/                     # (this dir — excluded from build)
    ├── 00-project-tracker.md
    ├── 01-decisions.md
    ├── 02-architecture.md
    ├── patterns/                  # Generic WP patterns (reused across projects)
    └── workflows/                 # PHPCS, commit workflow
```

---

## Class responsibilities

### `WPMail_DKIM_Sign\Plugin` (in `includes/class-plugin.php`)

Top-level orchestrator. Single global instance stored on `$GLOBALS['wpmail_dkim_sign_plugin']` (per the "Global Variable Pattern" in copilot-instructions.md).

- `__construct()` — does nothing heavy; just constructs sub-service references as `?Settings`, `?Keypair`, etc., all initially null.
- `run(): void` — registers hooks: `admin_menu`, `admin_init`, `admin_post_*`, `phpmailer_init`, `admin_enqueue_scripts`.
- Lazy getters: `get_settings(): Settings`, `get_keypair(): Keypair`, `get_signer(): Signer`, `get_verifier(): Verifier` — instantiate on first access, cache.
- **Exception to lazy-loading:** `Settings` is instantiated in `run()` immediately because it needs to register with `admin_init` before the first `admin_init` fires.

### `WPMail_DKIM_Sign\Settings` (in `includes/class-settings.php`)

Owns the admin UI and configuration persistence.

- `register_menu(): void` — `add_options_page()` under Settings.
- `register_hooks(): void` — registers `admin_post_wpmail_dkim_sign_save`, `admin_post_wpmail_dkim_sign_generate_key`, etc.
- `render_page(): void` — builds data array, loads `admin-templates/settings-page.php`.
- `handle_save(): void` — nonce + capability check, validate every field, persist, redirect with `settings_errors()`.
- `get(string $key, mixed $default = null): mixed` — read accessor over the settings options row.
- Sanitisers: `sanitize_domain()`, `sanitize_selector()`, `sanitize_bool()` (the last via `filter_var(FILTER_VALIDATE_BOOLEAN)` per house style).

### `WPMail_DKIM_Sign\Keypair` (in `includes/class-keypair.php`)

Owns the RSA-2048 keypair lifecycle.

- `generate(): bool` — `openssl_pkey_new()` → export PEM → extract public base64 → store. Returns true on success, false (with `error_log`) on OpenSSL failure.
- `store(string $private_pem, string $public_b64): void` — writes `OPT_KEYPAIR` with autoload off.
- `load(): ?array` — returns `['private_pem' => …, 'public_b64' => …, 'created_at' => …]` or null if absent. Static-cached.
- `delete(): void` — `delete_option`, clear static cache.
- `exists(): bool` — quick predicate.
- `get_public_dkim_value(): ?string` — returns the raw base64 (no PEM headers) suitable for the `p=` field.
- `format_txt_record(string $selector, string $domain): array` — returns `['name' => '…', 'value_full' => 'v=DKIM1; k=rsa; p=…', 'value_chunked' => '"chunk1" "chunk2" "chunk3"']`.

### `WPMail_DKIM_Sign\Signer` (in `includes/class-signer.php`)

Owns the actual signing trigger.

- `register_hooks(): void` — registers `phpmailer_init`.
- `sign( \PHPMailer\PHPMailer\PHPMailer $phpmailer ): void` — SESE; runs pre-flight; mutates `$phpmailer` only when all checks pass. Logs (gated on `WP_DEBUG`) the reason for any skip.
- Private helpers: `should_sign(PHPMailer $m): true|string` — returns `true` to proceed, or a string reason for skip; `extract_from_domain(PHPMailer $m): ?string`.

### `WPMail_DKIM_Sign\Verifier` (in `includes/class-verifier.php`)

Owns the diagnostic actions.

- `register_hooks(): void` — registers `admin_post_wpmail_dkim_sign_test_email`, `admin_post_wpmail_dkim_sign_check_dns`.
- `handle_test_email(): void` — nonce + capability → attach one-shot capture filter at priority 999 → `wp_mail()` → redirect back with transient-stored result.
- `handle_check_dns(): void` — `dns_get_record()` → parse `p=` from TXT segments → compare to local public base64 → redirect back with result.
- Private helpers: `capture_headers(PHPMailer $m): void` (the one-shot), `parse_dkim_txt(array $records): array` (extracts `p=` from raw TXT response, handles multi-string concatenation).

---

## Hook flow

### Admin path

```
admin_menu             → Settings::register_menu()
admin_init             → Settings::register_hooks()  (registers admin_post_* listeners)
                       → Verifier::register_hooks() (registers test/dns admin_post_*)
admin_enqueue_scripts  → Plugin::enqueue_admin_assets()  (only on our settings page)
admin_post_wpmail_dkim_sign_save           → Settings::handle_save()
admin_post_wpmail_dkim_sign_generate_key   → Settings::handle_generate_key()
admin_post_wpmail_dkim_sign_regenerate_key → Settings::handle_regenerate_key()
admin_post_wpmail_dkim_sign_delete_key     → Settings::handle_delete_key()
admin_post_wpmail_dkim_sign_test_email     → Verifier::handle_test_email()
admin_post_wpmail_dkim_sign_check_dns      → Verifier::handle_check_dns()
```

### Mail-signing path (per outbound mail)

```
wp_mail() called
  ↓
PHPMailer instance constructed by core
  ↓
do_action( 'phpmailer_init', $phpmailer )
  ↓
Signer::sign( $phpmailer )    ← priority 10 (us)
  ├─ check: enabled?           → if no, log + return
  ├─ check: key loads?         → if no, log + return
  ├─ check: DKIM_domain empty? → if no, log + return (don't override)
  ├─ check: From matches?      → if no, log + return (would misalign)
  └─ all good → set DKIM_domain, DKIM_selector, DKIM_private_string, DKIM_identity='',
                    DKIM_passphrase='', DKIM_copyHeaderFields=false (c=relaxed/simple, fixed by PHPMailer)
  ↓
[other phpmailer_init filters, if any]
  ↓
$phpmailer->send() → calls preSend() → signs message → emits DKIM-Signature header
```

### Test-email diagnostic path (one-shot capture)

```
admin_post_wpmail_dkim_sign_test_email fires
  ↓
Verifier::handle_test_email()
  ├─ nonce + capability check
  ├─ set $GLOBALS['wpmail_dkim_sign_capturing'] = true
  ├─ add_action( 'phpmailer_init', [$this, 'capture_headers'], 999, 1 )
  ├─ wp_mail( $recipient, $subject, $body )
  │    └─ phpmailer_init fires:
  │         ├─ Signer::sign() at priority 10
  │         └─ Verifier::capture_headers() at priority 999
  │              ├─ if $GLOBALS['wpmail_dkim_sign_capturing'] !== true → return
  │              ├─ set_transient( 'wpmail_dkim_sign_capture_' . get_current_user_id(),
  │              │                  $phpmailer->createHeader(), 5 * MINUTE_IN_SECONDS )
  │              └─ remove_action( 'phpmailer_init', [$this, 'capture_headers'], 999 )
  ├─ unset $GLOBALS['wpmail_dkim_sign_capturing']
  └─ wp_safe_redirect back to settings page with ?test=sent
  ↓
Settings page next render → reads transient → renders captured headers + verdict
```

---

## Data shapes

### `OPT_SETTINGS` (autoload on)

```php
[
    'domain'           => 'example.com',          // signing domain (sanitised, lowercase)
    'selector'         => 'website',              // DNS label (sanitised)
    'enabled'          => false,                  // boolean (stored as bool; on read normalised via FILTER_VALIDATE_BOOLEAN)
    'remove_on_uninstall' => false,               // boolean — controls uninstall.php behaviour
]
```

### `OPT_KEYPAIR` (autoload off)

```php
[
    'private_pem' => "-----BEGIN PRIVATE KEY-----\n…\n-----END PRIVATE KEY-----\n",
    'public_b64'  => 'MIIBIjANBgkqhkiG9w0BAQEFA…AB',  // raw base64, no PEM headers, no newlines
    'created_at'  => '2026-05-22 14:33:01 BST',       // human-readable per house style
]
```

### Transient `wpmail_dkim_sign_capture_{user_id}` (5 min TTL)

Plain string — the raw output of `$phpmailer->createHeader()` from the most recent test send by this user. Read-once: cleared as soon as the settings page renders it.

---

## Hook & nonce naming

All admin-post actions, nonces, and option keys live in `constants.php`. Names follow the pattern:

- Action constants: `ACTION_SAVE`, `ACTION_GENERATE_KEY`, `ACTION_TEST_EMAIL`, etc. with values like `'wpmail_dkim_sign_save'`
- Nonce constants: `NONCE_SAVE`, `NONCE_GENERATE_KEY`, etc. — single-use per form
- Option-row key constants: `OPT_SETTINGS`, `OPT_KEYPAIR`
- Default-value constants: `DEF_SELECTOR`, `DEF_ENABLED`, etc.
- Capability constant: `CAP_MANAGE` = `'manage_options'` (one definition, used everywhere)

---

## What lives outside the class boundary

Bootstrap (`wpmail-dkim-sign.php`):

```php
<?php
/**
 * Plugin Name: WP Mail DKIM Sign
 * Description: …
 * Version: 1.0.0
 * Requires PHP: 8.0
 * Requires at least: 6.0
 * Text Domain: wpmail-dkim-sign
 * License: GPL v2 or later
 */

defined( 'ABSPATH' ) || die();

require_once __DIR__ . '/constants.php';
require_once __DIR__ . '/includes/class-plugin.php';
// (sub-service classes are required lazily by class-plugin.php)

$GLOBALS['wpmail_dkim_sign_plugin'] = new \WPMail_DKIM_Sign\Plugin();
$GLOBALS['wpmail_dkim_sign_plugin']->run();
```

`uninstall.php`:

```php
<?php
defined( 'WP_UNINSTALL_PLUGIN' ) || die();

$settings = get_option( 'wpmail_dkim_sign_settings', [] );
if ( ! empty( $settings['remove_on_uninstall'] ) ) {
    delete_option( 'wpmail_dkim_sign_settings' );
    delete_option( 'wpmail_dkim_sign_keypair' );
} else {
    // Leave data in place — user did not opt in to data removal.
}
```

---

## What this architecture deliberately doesn't have

- No custom DB tables (`dev-notes/patterns/database.md` patterns don't apply — settings + keypair fit comfortably in `wp_options`).
- No REST API endpoints (admin-post handlers are sufficient for two diagnostic buttons).
- No background cron (no periodic work to do).
- No transients beyond the test-email capture (deliberate — DNS check is intentionally uncached per HANDOFF).
- No translations beyond the `text-domain` declaration + `__()` wrappers; `.pot` generation is a v1.1 task.
