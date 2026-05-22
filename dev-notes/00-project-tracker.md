# Project Tracker

**Version:** 0.1.0
**Last Updated:** 2026-05-22
**Current Phase:** Acceptance pass complete
**Overall Progress:** 100% for v1 scope

---

## Overview

`wpmail-dkim-sign` is a WordPress plugin that DKIM-signs outbound `wp_mail()` messages at the WP / PHPMailer layer, so the `DKIM-Signature:` header is attached before the message leaves the site to the upstream MTA. Built primarily as a quick fix for Microsoft's late-April-2026 tightening on unsigned mail (silent rejection / Junk routing of SPF+DMARC-valid but DKIM-less messages). Intended for public distribution under GPLv2-or-later.

Full spec: [`/HANDOFF.md`](../HANDOFF.md). Architecture and resolved decisions: [`01-decisions.md`](01-decisions.md) and [`02-architecture.md`](02-architecture.md).

---

## Active TODO Items

All v1 milestones (M1–M7) complete. See "Acceptance Criteria Status" below for the per-criterion evidence trail.

Post-v1 backlog (not blocking v1 ship):

- [ ] Distribution (M8) — build zip honouring `.distignore`, fresh-WP install test, companion blog post on headwall-hosting.com.
- [ ] Optional WP.org plugin directory submission.
- [ ] Roll out across the Headwall client fleet (chroot openssl fix automated separately).

## Acceptance Criteria Status

Mapped from `HANDOFF.md`:

| # | Criterion | Status | Evidence |
|---|-----------|--------|----------|
| AC1 | Activates/deactivates clean under WP_DEBUG, no warnings | ✅ | Live on devx.headwall.tech; PHPCS clean across all PHP files |
| AC2 | Generate produces valid RSA-2048 keypair; public matches private | ✅ | M2 wp-cli smoke test verified public_b64 derived from private PEM equals stored value |
| AC3 | wp_mail() produces a validating DKIM-Signature header | ✅ | M5 wp-cli test produced header with all 8 RFC 6376 tags; live test confirmed via Gmail |
| AC4 | Real Microsoft mailbox shows dkim=pass + dmarc=pass | ✅ | Production test 2026-05-22 on permajet.com → outlook.com: `dkim=pass header.d=permajet.com; dmarc=pass; compauth=pass reason=100` (see memory entry project-production-validation) |
| AC5 | Plugin disabled → no DKIM-Signature added | ✅ | M5 signer test Case 2 |
| AC6 | Existing DKIM_domain → no double-sign | ✅ | M5 signer test Case 4 (PHPMailer property preserved unchanged) |
| AC7 | From: domain mismatch → unsigned + logged | ✅ | M5 signer test Cases 3, 5; Signer::log_skip writes via error_log when WP_DEBUG |
| AC8 | Direct HTTP access returns no output | ✅ | M7 audit: every PHP file has `defined('ABSPATH') \|\| die()` or `defined('WP_UNINSTALL_PLUGIN')` guard; verified by direct-include test |
| AC9 | Check DNS button reports match when record published | ✅ | M6 wp-cli verifier test: `lookup_and_compare` returns status "match" when published p= equals local; "mismatch"/"nxdomain"/"parse_error"/"no_local_key" branches all exercised |
| AC10 | Uninstall checkbox controls data removal | ✅ | M7 uninstall.php simulation: unticked → options preserved; ticked → options removed cleanly |

---

## Milestones

Each milestone is a vertically-thin slice that ends in a checkable state. The acceptance criteria from HANDOFF.md (AC1–AC10) are distributed across milestones and referenced at the bottom of each section.

### Milestone 1 — Project scaffold

Goal: Plugin file structure in place, activates and deactivates cleanly with no PHP warnings.

- [ ] `wpmail-dkim-sign.php` — plugin header, `ABSPATH` guard, bootstrap that loads constants and `class-plugin.php`, instantiates `Plugin`, calls `run()`
- [ ] `constants.php` — namespace declaration, `OPT_*` and `DEF_*` constants for option keys, defaults, capabilities, action/nonce names
- [ ] `includes/class-plugin.php` — main `Plugin` class with `run()` registering all hooks; lazy-loads sub-services
- [ ] `includes/class-settings.php` — class skeleton, `__construct`, empty `register_hooks()` method
- [ ] `includes/class-keypair.php` — class skeleton, properties for cached key material
- [ ] `includes/class-signer.php` — class skeleton with `phpmailer_init` handler stub
- [ ] `includes/class-verifier.php` — class skeleton for test-email and DNS-check handlers
- [ ] `uninstall.php` — guarded skeleton (option-removal opt-in not yet wired)
- [ ] `phpcs.xml` — WordPress ruleset, prefixes `WPMail_DKIM_Sign` / `wpdkm` / `WPDKM` (per D12), exclude `dev-notes/`, `assets/`
- [ ] `readme.txt` — WP.org-format skeleton with stable tag, contributors, short/long description placeholders
- [ ] `LICENSE` — GPLv2-or-later text
- [ ] `assets/admin/` — placeholder dir for later CSS/JS
- [ ] `admin-templates/` — placeholder dir for later view files

**Acceptance:** Activate plugin on a fresh WP install with `WP_DEBUG=true` + `WP_DEBUG_LOG=true` — no warnings, no notices. Deactivate — no warnings. Direct HTTP access to any PHP file returns nothing. _(Satisfies AC1, AC8.)_

---

### Milestone 2 — Key management

Goal: Generate / load / delete RSA-2048 keypair from the admin UI; no auto-generation on activation.

- [ ] `Keypair::generate()` — `openssl_pkey_new()` with `OPENSSL_KEYTYPE_RSA`, 2048 bits; extract private PEM via `openssl_pkey_export()`; extract public key material via `openssl_pkey_get_details()`
- [ ] `Keypair::store()` — write single `wp_options` row with autoload disabled (key: `OPT_KEYPAIR`); structure: `['private_pem' => …, 'public_b64' => …, 'created_at' => human-readable-string-Y-m-d-H:i:s-T]`
- [ ] `Keypair::load()` — read option, validate via `openssl_pkey_get_private()`; static cache on the class so repeated loads in one request hit memory
- [ ] `Keypair::delete()` — `delete_option()`, clear static cache
- [ ] `Keypair::exists()` — boolean helper
- [ ] `Keypair::get_public_dkim_value()` — return raw base64 public key (no PEM headers, no newlines), used to build the `p=` portion of the TXT record
- [ ] Admin POST handlers wired (nonce-protected, `manage_options`-gated) for: generate, regenerate, delete
- [ ] JS `confirm()` on regenerate + delete buttons (in `assets/admin/admin.js`, not inline)

**Acceptance:** Click "Generate key pair" — option appears in DB with autoload off. Click "Delete key" with confirmation — option removed. Click "Regenerate" — old key replaced. Public key base64, when decoded, matches the public-key component of the private key. _(Satisfies AC2.)_

---

### Milestone 3 — Settings page + form handling

Goal: Admin page renders, settings persist, all writes are nonce + capability protected.

- [ ] `Settings::register_menu()` — `add_options_page()` under "Settings → WP Mail DKIM" (or top-level — TBD in `01-decisions.md`)
- [ ] `Settings::render_page()` — loads `admin-templates/settings-page.php` with prepared data array (no logic in view file)
- [ ] `admin-templates/settings-page.php` — code-first `printf()` rendering using small render helpers (data-driven, per copilot-instructions.md)
- [ ] `Settings::handle_save()` — admin-post.php endpoint or `admin_init` save handler; nonce + capability check; sanitize each field via dedicated validators
- [ ] Validators: domain (lowercase, `filter_var(..., FILTER_VALIDATE_DOMAIN)` + `FILTER_FLAG_HOSTNAME`), selector (DNS-label regex `^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$`, case-insensitive), enabled (`FILTER_VALIDATE_BOOLEAN`), canonicalisation (whitelist: `simple/simple`, `relaxed/simple`, `relaxed/relaxed`)
- [ ] Default domain logic: `parse_url(home_url(), PHP_URL_HOST)` minus leading `www.`
- [ ] Default selector: `website`
- [ ] Default enabled: `false` (off until DNS confirmed)
- [ ] Default canonicalisation: `relaxed/relaxed`
- [ ] Settings stored in single `wp_options` row at `OPT_SETTINGS`, autoload **on** (read on every request)
- [ ] Admin notices for save success / validation errors via `settings_errors()` or transient-based notice queue

**Acceptance:** Settings save and persist across reloads. Invalid domain / selector rejected with clear error. Missing or invalid nonce rejected. Non-admin user cannot reach the save handler. _(Partial AC1; foundational for AC3, AC5, AC6, AC7.)_

---

### Milestone 4 — DNS record display

Goal: Once a key exists, show the DNS TXT record value in both single-string and quoted-chunks forms.

- [ ] On settings page (when a key exists), render:
    - Record name: `{selector}._domainkey.{domain}`
    - Record type: `TXT`
    - Full value: `v=DKIM1; k=rsa; p={base64}`
    - Chunked form: split full value at 255-char boundaries, each chunk wrapped in double quotes, joined by newlines
- [ ] `Keypair::format_txt_record()` returns both forms as an array
- [ ] Copy-to-clipboard buttons on each form (JS, non-blocking; works as plain text if JS disabled)
- [ ] Inline explanation: which form to paste, plus link to a follow-up help section

**Acceptance:** Displayed `v=DKIM1; k=rsa; p=…` parses correctly when validated against the public key in storage. Chunked form correctly splits at 255 chars, never breaks the base64 inside a chunk boundary in a way that an additive concatenation would fail to recover. _(Foundational for AC9.)_

---

### Milestone 5 — Signing hook

Goal: `phpmailer_init` sets `DKIM_*` properties when pre-flight checks pass; safely no-op otherwise.

- [ ] `Signer::register_hooks()` — `add_action('phpmailer_init', [$this, 'sign'], 10, 1)`
- [ ] `Signer::sign( \PHPMailer\PHPMailer\PHPMailer $phpmailer ): void` — single-entry-single-exit; runs pre-flight checks, sets `$result` state, only mutates `$phpmailer` at the end of the function
- [ ] Pre-flight checks (every "skip" branch logs at debug level via `error_log()` gated on `WP_DEBUG`):
    1. Plugin is enabled in settings — else skip
    2. Private key exists and loads via `openssl_pkey_get_private()` — else skip
    3. `empty( $phpmailer->DKIM_domain )` — else skip (don't override)
    4. From-domain on the message matches configured signing domain (case-insensitive comparison, after `strtolower()`) — else skip
- [ ] From-domain extraction: parse `$phpmailer->From` (the actual envelope from PHPMailer at hook time; do not trust `wp_mail_from` filter blindly per HANDOFF)
- [ ] On all checks passing, set:
    - `DKIM_domain` ← configured domain
    - `DKIM_selector` ← configured selector
    - `DKIM_private_string` ← PEM private key (string variant only; never write key to disk)
    - `DKIM_identity` ← `''`
    - `DKIM_passphrase` ← `''`
    - `DKIM_copyHeaderFields` ← `false` (avoid duplicating headers)
    - Header canonicalisation + body canonicalisation derived from `$canon = 'relaxed/relaxed'` → split on `/` → set `DKIM_header_canonicalization`, `DKIM_body_canonicalization` (PHPMailer property names)
- [ ] Static-cache the loaded key on `Keypair` so a single request sending N mails doesn't re-query `wp_options` N times
- [ ] PHPCS-clean

**Acceptance:** With plugin enabled, key generated, DNS record published, `wp_mail()` to an external address arrives with a `DKIM-Signature:` header that validates against the published TXT record. With plugin disabled, no `DKIM-Signature:` is added. With another plugin having already set `DKIM_domain`, this plugin does not double-sign. From-domain mismatch leaves the mail unsigned and logs a notice. _(Satisfies AC3, AC5, AC6, AC7.)_

**M5 production validation 2026-05-22:** Real-world test on `permajet.com` (live client site) → outlook.com personal mailbox returned `dkim=pass header.d=permajet.com; dmarc=pass action=none; compauth=pass reason=100`. AC3 + AC4 both green against the actual Microsoft endpoint that prompted the project. Reference baseline captured in memory entry [project-production-validation](../../../../../home/pfaulkner/.claude/projects/-var-www-devx-headwall-tech-web-wp-content-plugins-wpmail-dkim-sign/memory/project-production-validation.md).

---

### Milestone 6 — Verification UI

Goal: Two diagnostic actions on the settings page — "Send test email" and "Check DNS record".

- [ ] `Verifier::handle_test_email()` — admin-post handler; nonce + capability gated
- [ ] Recipient field on the settings page: text input pre-filled with `get_option('admin_email')`, editable. (Decision Q3: resolved — prompt with admin-email default.)
- [ ] During the test send, attach a one-shot `phpmailer_init` filter that runs **after** the signer (higher priority number) and captures `$phpmailer->createHeader()` into a transient keyed by the current user ID; then `wp_mail()` the test message
- [ ] On redirect back to the settings page, read transient, render headers in a `<pre>`, scan for `DKIM-Signature:` line, render diagnostic verdict: "Looks good (DKIM-Signature present)" / "Missing — pre-flight reason: X"
- [ ] `Verifier::handle_dns_check()` — admin-post handler; nonce + capability gated
- [ ] `dns_get_record( "{selector}._domainkey.{domain}", DNS_TXT )`; concatenate `txt` segments per record entry (DNS providers may split); extract `p=` value via regex; compare to locally-stored public base64
- [ ] Possible outcomes rendered: `match` / `mismatch` (show both base64 values truncated) / `nxdomain` / `parse_error`
- [ ] Do **not** cache the DNS result — propagation is the most common troubleshooting question

**Acceptance:** Test email arrives at chosen recipient with DKIM-Signature; settings page shows captured headers. "Check DNS record" with the correct record published returns match; with no record returns NXDOMAIN; with a stale or wrong p= value returns mismatch with a clear before/after diff. _(Satisfies AC4 verification path, AC9.)_

---

### Milestone 7 — Uninstall + polish + acceptance pass

Goal: Final polish, opt-in uninstall, full HANDOFF acceptance criteria run-through.

- [ ] "Remove all data on uninstall" checkbox in settings (stored in the settings options row)
- [ ] `uninstall.php` — reads the flag; if true, `delete_option(OPT_SETTINGS)` and `delete_option(OPT_KEYPAIR)`; if false, no-op
- [ ] Final `readme.txt` content (description, installation steps, FAQ covering Microsoft context, DNS chunking, from-domain mismatch, Amavis body-rewrite gotcha)
- [ ] Final `LICENSE` (GPLv2-or-later)
- [ ] Full PHPCS pass — clean
- [ ] Manual verification: send test mail to a real outlook.com or M365 mailbox, inspect `Authentication-Results:` header for `dkim=pass` and `dmarc=pass`
- [ ] Re-walk all 10 HANDOFF acceptance criteria; record results in this tracker

**Acceptance:** All 10 criteria from HANDOFF.md "Acceptance criteria" pass.

---

### Milestone 8 — Distribution (post-build)

Goal: Plugin packaged and ready for redistribution.

- [ ] Generate distribution zip honouring `.distignore` (exclude `dev-notes/`, `.github/`, `.git/`, `phpcs.xml`, etc.)
- [ ] Test install of the zip on a fresh WP instance
- [ ] Companion blog post draft on headwall-hosting.com (Microsoft context, install walk-through with screenshots, DNS chunking gotcha, verification via outlook.com mailbox)
- [ ] (Optional) Submit to wordpress.org plugin directory

**Acceptance:** Zip installs cleanly on a vanilla WP site, no dev artefacts shipped, blog post published.

---

## Technical Debt

(Empty for v1 — populate as we accumulate.)

Known v1.x deferrals (not debt per se, but worth tracking):

- Multi-domain signing (single key, multiple `From:` domains alignment)
- Key rotation UI (currently: regenerate + republish manually)
- Ed25519 keys (RFC 8463) — patchy DNS/receiver support today, revisit late 2026
- Rolling buffer of recently-signed headers in a transient for support diagnostics (Decision Q4: punted to v1.1)
- Signing mail sent by code paths that bypass `wp_mail()` (raw `mail()`, plugin-bundled SMTP libraries) — documented limitation, not implemented

---

## Notes for Development

- Style and structure: `.github/copilot-instructions.md` + `dev-notes/patterns/*` are authoritative. They override `HANDOFF.md` where they conflict.
- Decisions log: [`01-decisions.md`](01-decisions.md).
- Architecture: [`02-architecture.md`](02-architecture.md).
- Public distribution: any new dependency requires explicit approval. Default position: zero composer deps, zero bundled libraries. Use the PHPMailer that ships with WP core.
- Commit workflow: `phpcs` → `phpcbf` → `phpcs` → commit. See [`workflows/commit-to-git.md`](workflows/commit-to-git.md).
- Test environment: this working copy lives under `/var/www/devx.headwall.tech/` — the dev sandbox on nexus. Test against a real outlook.com / M365 mailbox before declaring AC4 satisfied.
