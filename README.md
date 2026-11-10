# WP Mail DKIM Sign

[![Version](https://img.shields.io/github/v/release/headwalluk/wpmail-dkim-sign?label=version&color=blue)](https://github.com/headwalluk/wpmail-dkim-sign/releases/latest)
[![PHP](https://img.shields.io/badge/PHP-8.0+-purple.svg)](https://www.php.net/)
[![WordPress](https://img.shields.io/badge/WordPress-6.0+-21759B.svg)](https://wordpress.org/)
[![License](https://img.shields.io/badge/license-GPL--2.0+-green.svg)](LICENSE)
[![Coding Standards](https://img.shields.io/badge/WordPress-Coding%20Standards-blue.svg)](https://github.com/WordPress/WordPress-Coding-Standards)

DKIM-sign outbound WordPress mail at the WP layer, so a valid DKIM-Signature header is attached before the message reaches your upstream SMTP relay.

## Who it's for

WordPress sites whose outbound mail goes through an SMTP relay that does not sign DKIM on their behalf — including sites relayed via shared hosting MTAs (Postfix + Amavis, ISPConfig, commercial mail services) where per-domain DKIM signing isn't enrolled at the relay.

Particularly relevant since Microsoft (Outlook.com, Hotmail, M365) tightened authentication requirements in early 2026 and now silently rejects or junks unsigned mail even with valid SPF and DMARC. If your client mail is landing in Microsoft Junk despite SPF and DMARC being green, this plugin almost certainly fixes it.

## What it does

- Hooks `phpmailer_init` to attach a valid DKIM-Signature header to `wp_mail()` output
- Self-service RSA-2048 key generation in the admin — no shell access required
- DNS TXT record display with separate **Host name** and **Full record name** copy options, plus a chunked value for DNS providers that limit string length
- **Send test email** button captures the prepared headers post-signing so you can confirm the signature without leaving the admin
- **Check DNS record** button does a fresh DNS lookup, compares the published `p=` to the locally-stored public key, and actively probes for common misconfigurations (doubled zone, missing `_domainkey` label)
- Aligns the DKIM `d=` parameter with the From: header domain — DMARC alignment passes
- Steps aside cleanly if another plugin (WP Mail SMTP, FluentSMTP, etc.) is already configured to sign — no double-signing
- Auto-updates via the bundled GitHub updater

## Install

1. Download `wpmail-dkim-sign.zip` from the [latest release](https://github.com/headwalluk/wpmail-dkim-sign/releases/latest)
2. WordPress admin → Plugins → Add New → Upload Plugin → choose the zip → Install Now → Activate
3. Settings → WP Mail DKIM to configure

Future updates arrive automatically via the bundled GitHub updater. See [`docs/installation.md`](docs/installation.md) for the full setup walkthrough including DNS publication and verification.

### Requirements

- WordPress 6.0 or later
- PHP 8.0 or later
- OpenSSL extension for PHP (almost always present)

If your PHP-FPM pool runs in a chroot jail, OpenSSL needs a small additional config inside the jail — see [`docs/self-hosting.md`](docs/self-hosting.md).

## Documentation

Full user and developer documentation lives in [`docs/`](docs/):

- [Installation walkthrough](docs/installation.md): from install to a verified `dkim=pass`
- [How it works](docs/how-it-works.md): what gets signed, the pre-flight checks, canonicalisation
- [Configuration](docs/configuration.md): every setting, key rotation, data retention
- [Troubleshooting](docs/troubleshooting.md): DNS mistakes, unsigned mail, body-rewriting relays
- [Self-hosting](docs/self-hosting.md): requirements, stored data, chroot OpenSSL configuration
- [Hooks and filters](docs/developers/hooks-and-filters.md) *(for developers)*

## License

GPL v2 or later. See [LICENSE](LICENSE).
