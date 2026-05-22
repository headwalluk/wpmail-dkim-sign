# Decisions Log

**Last Updated:** 2026-05-22

Records architectural decisions and resolved open questions, with the reasoning. Add to this file as new decisions arise during build.

---

## Resolved open questions from HANDOFF.md

### D1 — Plugin slug, namespace, prefixes

- **Decision:** slug `wpmail-dkim-sign`, namespace `WPMail_DKIM_Sign`, text-domain `wpmail-dkim-sign`. Dual-prefix scheme for global identifiers — see D12.
- **Date:** 2026-05-22.
- **Reasoning:** Neutral, community-friendly, matches the working directory name on disk. Avoids brand-forward `headwall-dkim-signer` for public distribution, and avoids land-grabbing the generic `wp-simple-dkim` slot.
- **Affects:** plugin main file name (`wpmail-dkim-sign.php`), all class namespaces.

### D2 — PHP minimum version

- **Decision:** PHP 8.0+ (per `.github/copilot-instructions.md`).
- **Date:** 2026-05-22.
- **Reasoning:** Matches Paul's house style across the Headwall plugin portfolio. Enables type hints, union types, nullable types, named args. Narrows the install base relative to PHP 7.4 (HANDOFF's original target) but the gap is closing — WP itself recommends 8.x. Plugin readme will state PHP 8.0+ requirement clearly.
- **Tradeoff acknowledged:** sites still on 7.4 cannot install the plugin. Acceptable given the broader hosting industry has moved past 7.4 EOL (Nov 2022).

### D3 — Test-email recipient behaviour

- **Decision:** Recipient input field on the settings page, pre-filled with `get_option('admin_email')` but editable.
- **Date:** 2026-05-22.
- **Reasoning:** The genuinely useful test target is an external mailbox the user controls (typically an outlook.com / hotmail mailbox they keep specifically for inbound testing) — that exercises the actual Microsoft problem case. Defaulting to admin-email keeps a one-click path for users who just want a sanity check.

### D4 — Recently-signed-emails debug buffer

- **Decision:** Punt to v1.1.
- **Date:** 2026-05-22.
- **Reasoning:** v1 verification UI (test-email + DNS-check) covers the common diagnostic cases. Adding a rolling transient now adds storage and UI complexity for a benefit we haven't yet sized against real support load. Revisit once we have v1 in customer hands and know what support questions actually look like.

---

## Architecture decisions made during planning

### D5 — Admin menu placement

- **Decision:** Settings → "WP Mail DKIM" (sub-menu under Settings), not a top-level menu.
- **Reasoning:** It's a configuration plugin used rarely after install. Top-level menu adds visual noise for site editors who never touch it.

### D6 — Option-row layout

- **Decision:** Two separate `wp_options` rows: `OPT_SETTINGS` (autoload **on**) and `OPT_KEYPAIR` (autoload **off**).
- **Reasoning:**
    - Settings (domain, selector, enabled, canonicalisation) read on every request that hits `phpmailer_init` — must be in the autoloaded options to avoid an extra DB hit per mail.
    - Keypair (private PEM, public base64, created-at) is large (~3 KB for an RSA-2048 PEM) and only loaded during admin operations and during actual mail signing. Keeping it out of the autoload set saves memory on every other request.
    - Splitting also makes the "delete keypair" admin action atomic — settings (and the `enabled` flag) survive a key delete, so the user can't accidentally lose their configuration when rotating keys.

### D7 — Key storage form: PEM in options, not files

- **Decision:** Private key stored as PEM string in `wp_options`. No file written to disk at any point during signing.
- **Reasoning:** HANDOFF explicitly mandates this. Filesystem layout varies wildly across managed-WP hosts; some are read-only at runtime. Options-row storage is the only universally-writable spot. PHPMailer's `DKIM_private_string` property accepts the PEM directly — no file path needed.
- **Risk:** anyone with DB read access has the private key. Acceptable: anyone with DB read access can also impersonate the site in many other ways. The DKIM key compromise is recoverable by regenerate + republish; broader DB compromise is the larger incident.

### D8 — Static caching of loaded key per request

- **Decision:** `Keypair` class holds a `private static ?array $cache` populated on first load and reused for the rest of the request.
- **Reasoning:** A WP request can send multiple emails (Woo order: customer + admin notification + back-in-stock + low-stock + …). Re-querying `wp_options` and re-parsing the PEM on each is wasteful. Static-life cache is correct: never persists across requests (so a regenerate immediately takes effect), no invalidation logic needed.

### D9 — Hook priority for signer

- **Decision:** `add_action( 'phpmailer_init', [$signer, 'sign'], 10, 1 )` — default priority 10.
- **Reasoning:** Other plugins that set `DKIM_domain` typically also run at default priority. Our pre-flight checks `empty( $phpmailer->DKIM_domain )` and skips if already set, which makes the order *between same-priority plugins* irrelevant: whoever runs first wins. Going earlier (priority 5) would compete with mail-from-rewriters and could lead to signing the wrong From: domain.

### D10 — Verifier hook strategy for the test-email diagnostic

- **Decision:** During the test-send code path, add a one-shot `phpmailer_init` filter at priority 999 (i.e. runs *after* the signer at priority 10), which captures `$phpmailer->createHeader()` into a transient, then immediately removes itself.
- **Reasoning:**
    - Capturing the headers in their fully-prepared form (post-signing) is the only way to show the user "did the DKIM-Signature actually attach?"
    - Doing this only during a deliberate test send (gated by a per-request flag) keeps the diagnostic out of the production mail path entirely — no performance hit for normal mail, no risk of leaking captured headers to other admins.
    - The one-shot pattern: filter calls `remove_action( 'phpmailer_init', $self, 999 )` from inside its own callback after writing the transient.

### D11 — Canonicalisation default

- **Decision:** `relaxed/relaxed` (already specified in HANDOFF, recording the why here).
- **Reasoning:** Tolerates whitespace normalisation, header-case changes, and header folding differences. Critical given the Amavis-on-nexus relay path: Amavis can rewrite headers in ways that break `simple` canonicalisation. The setting remains user-changeable for sites where the operator wants stricter signing and knows their relay path won't mutate.

---

### D12 — Dual-prefix scheme for global identifiers

- **Decision:** Use a short prefix (`WPDKM_` / `wpdkm_`) for everything that lives in the global PHP namespace OR appears as a wp_options-table row, OR appears as an external string identifier (admin-post action name, nonce action, transient key). Reserve the long-form namespace `WPMail_DKIM_Sign\` for class code only.
- **Date:** 2026-05-22.
- **Reasoning:** Long namespace reads well in PHP class code (`new WPMail_DKIM_Sign\Plugin()` is unambiguous and self-documenting). But it's noisy in:
    - DB inspection (`wp_options` row keys like `wpmail_dkim_sign_settings` are long and visually heavy).
    - Form payloads (`<input name="action" value="wpmail_dkim_sign_save">`).
    - URL query strings.
    - Global PHP constants (`WPDKM_PATH` reads cleaner than `WPMAIL_DKIM_SIGN_PATH`).
- **Concrete rules:**
    - Root-namespace `define()` constants → `WPDKM_*`. Examples: `WPDKM_VERSION`, `WPDKM_FILE`, `WPDKM_PATH`, `WPDKM_URL`, `WPDKM_BASENAME`.
    - Root-namespace functions → `wpdkm_*`. Example: `wpdkm_run()` (the bootstrap entry).
    - String VALUES of namespaced constants → `wpdkm_*` (the constant NAME stays inside the namespace, e.g. `WPMail_DKIM_Sign\OPT_SETTINGS = 'wpdkm_settings'`).
    - wp_options row keys → `wpdkm_*`. Examples: `wpdkm_settings`, `wpdkm_keypair`.
    - Admin-post action names → `wpdkm_*`. Examples: `wpdkm_save`, `wpdkm_generate_key`.
    - Nonce action names → `wpdkm_*`. Examples: `wpdkm_save_nonce`.
    - Transient key prefixes → `wpdkm_*`. Example: `wpdkm_capture_{user_id}`.
- **PHPCS implication:** `phpcs.xml` `WordPress.NamingConventions.PrefixAllGlobals` `prefixes` array must include both `WPMail_DKIM_Sign` (class code) and `wpdkm` / `WPDKM` (everything else in the global namespace).
- **Accessor function:** sub-services reach the orchestrator via `WPMail_DKIM_Sign\get_plugin()` (defined in `functions-private.php`). Inside the namespace, the unqualified call `get_plugin()` resolves correctly.

---

## Open decisions still to make (for later milestones)

None blocking M1–M3. Track here as they arise.
