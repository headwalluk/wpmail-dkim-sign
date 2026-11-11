# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

**Requires PHP 8.2 and WordPress 6.9.** Sites below either are not offered this update.

### Changed

- Minimum versions raised from PHP 8.0 and WordPress 6.0 to PHP 8.2 and WordPress 6.9.
- Signing, the test-email capture and the admin asset loader now check what `phpmailer_init` and `admin_enqueue_scripts` pass them. If another plugin passes something other than a PHPMailer object, the message goes out unsigned, with the reason logged when `WP_DEBUG` is on, instead of a fatal error.
- The admin styles use CSS logical properties, so the result borders sit on the correct side in right-to-left languages.
- `CLAUDE.md` now follows the shared Headwall plugin conventions and holds only what is specific to this plugin.

### Removed

- `readme.txt`. The plugin isn't distributed through WordPress.org, and the updater builds its details panel from the plugin header. `README.md` and `docs/` hold its content, and release builds now check only the plugin header and the version constant against the tag.

## [1.1.0] - 2026-11-10

### Removed

- The **Canonicalisation** setting. It never took effect: PHPMailer, which WordPress sends mail through, hardcodes `c=relaxed/simple` and has no way to change it, so every signature from 1.0.0 was already `relaxed/simple`, whatever was selected. Signing is unchanged; the Setup tab now shows the fixed value instead of a dropdown. The stored choice is ignored and dropped the next time the settings are saved. Introduced in 1.0.0.

### Added

- A **Settings** link under the plugin on the Plugins screen, for users who can manage options.
- Documentation: [How it works](docs/how-it-works.md) and [Configuration](docs/configuration.md), including a key rotation procedure that avoids failed signatures.

### Changed

- The bundled GitHub updater is now the shared version used across Headwall plugins. It runs only on admin, cron and WP-CLI requests, waits an hour after a failed GitHub lookup before trying again, logs real failures even without `WP_DEBUG`, and reports when the plugin header and version constant disagree. The `wpdkm_updater_enabled` filter is unchanged, and now also accepts values such as `'0'` or `'no'`. Its cache transient is now `wpmail_dkim_sign_github_release`; the old `wpdkm_github_release` expires by itself within 12 hours. Deleting the plugin with **Remove all plugin data** ticked now clears the updater cache too.
- The developer documentation moved from `docs/developers/filters.md` to [`docs/developers/hooks-and-filters.md`](docs/developers/hooks-and-filters.md).
- Release builds now fail if the plugin header, the version constant and the `readme.txt` stable tag don't all match the release tag.

### Fixed

- PHP 8.2 and later raised two "Creation of dynamic property" deprecation notices for every signed message, from the canonicalisation properties PHPMailer doesn't have. Introduced in 1.0.0.
- The test email's subject showed HTML entities in the site name, such as `&amp;` for `&`. Introduced in 1.0.0.
- Documentation: the `wpdkm_dns_zone` filter page said returning an empty string hides the host name form; it doesn't, and the page now gives a working alternative. Several button and field names in the guides didn't match the admin screens, and the troubleshooting guide described an alignment failure the plugin can't produce.

## [1.0.0] - 2026-05-22

### Added

- Initial public release.
- Signs outbound `wp_mail()` output by setting PHPMailer's DKIM properties at `phpmailer_init` priority 10. Single signing domain, RSA-2048.
- Pre-flight checks: skips cleanly when the plugin is disabled, when no key is stored, when `DKIM_domain` is already set by another plugin, or when the `From:` domain does not match the configured signing domain. Skip reasons logged at debug level via `error_log` when `WP_DEBUG` is on.
- Self-service key management: Generate, Regenerate, Delete buttons in the admin. RSA-2048 keys stored as a single `wp_options` row with autoload disabled. Private key never written to disk.
- DNS TXT record display with two copy options — Host name (recommended; auto-derived by stripping the SOA-detected zone from the FQDN) and Full FQDN. Chunked-255 quoted-strings form shown alongside the single-string form for older DNS providers.
- Built-in Send Test Email — captures the prepared headers post-signing via a one-shot `phpmailer_init` filter at priority 999, surfaces the DKIM-Signature line for inspection. Includes the most recent pre-flight skip reason when the test was left unsigned.
- Built-in Check DNS Record — fresh uncached `dns_get_record` lookup, compares the published `p=` to the locally-stored public key, reports `match` / `mismatch` / `nxdomain` / `parse_error` / `no_local_key`. On NXDOMAIN, probes for two common misconfigurations: doubled zone (`<intended>.<zone>`) and missing `_domainkey` label (`<selector>.<domain>`), with corrective guidance.
- Settings: signing domain (default = `home_url()` host minus `www.`), selector (default `website`), enabled flag (default off — plugin is a no-op until ticked), canonicalisation (default `relaxed/relaxed`), and an opt-in "Remove all data on uninstall" checkbox.
- Tabbed admin UI (Setup / Verify) with hash-based navigation, copy-to-clipboard for all relevant fields, and JS confirms on destructive actions (Regenerate, Delete).
- Bundled GitHub auto-updater — checks the `headwalluk/wpmail-dkim-sign` repo for newer releases and serves them as standard plugin updates. 12h cache; disable per-site via the `wpdkm_updater_enabled` filter.
- Auto-detected DNS zone via SOA walk, used to derive the Host-name shortcut and to construct misconfiguration probes. 24h cache; override via the `wpdkm_dns_zone` filter.
- Two PHP filters: `wpdkm_updater_enabled`, `wpdkm_dns_zone`.

### Verified

- Activates cleanly on a fresh WP install with `WP_DEBUG=true` and `WP_DEBUG_LOG=true`, no warnings.
- Produces a properly-formed DKIM-Signature header (all 8 RFC 6376 tags: `v`, `a`, `c`, `d`, `s`, `h`, `bh`, `b`).
- Real-world validation 2026-05-22: test mail from a live production site, relayed via a Postfix + Amavis smarthost, to an outlook.com personal mailbox returned `dkim=pass; dmarc=pass action=none; compauth=pass reason=100`, Microsoft's strongest explicit authentication signal.
