# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed

- The bundled GitHub updater is now the shared portable version used across Headwall plugins. It loads only on admin, cron and WP-CLI requests, waits an hour after a failed GitHub lookup before retrying, logs genuine failures even without `WP_DEBUG`, and warns when the plugin header and version constant disagree. The `wpdkm_updater_enabled` filter is unchanged. The release cache transient is now `wpmail_dkim_sign_github_release`; the old `wpdkm_github_release` expires on its own within 12 hours. Uninstalling with "Remove all data" also clears the updater cache.

### Fixed

- The canonicalisation setting had no effect: every message was signed `c=relaxed/simple` whichever mode was selected. PHPMailer hardcodes that value and has no property to change it, so the plugin was setting two properties PHPMailer never reads, which also raised "Creation of dynamic property" deprecation notices on PHP 8.2+. The setting is removed and the Setup tab now shows the fixed `relaxed/simple` value. Introduced in 1.0.0.
- The test email subject showed HTML entities from the site name (`&amp;` instead of `&`). The site name is now decoded before it goes into the subject. Introduced in 1.0.0.

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
