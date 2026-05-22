=== WP Mail DKIM Sign ===
Contributors: headwallhosting
Tags: dkim, email, deliverability, dmarc, smtp
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

DKIM-sign outbound wp_mail() messages at the WordPress layer, before they reach your upstream MTA. RSA-2048, single signing domain.

== Description ==

Since approximately late April 2026, Microsoft (Outlook.com, Hotmail.com, Live.com, and many Microsoft 365 tenants) has tightened authentication on inbound mail. Messages with valid SPF and DMARC but no DKIM signature are now frequently silently rejected or filed to Junk.

If your WordPress site sends transactional email through an SMTP relay that does not sign on your behalf, this plugin attaches a DKIM signature inside WordPress — before the message ever leaves the site — so it survives any onward relay and arrives at Microsoft (and elsewhere) properly authenticated.

**Key points:**

* Self-service key generation in the WP admin — no shell access required.
* Copy-paste DNS TXT record display, including a chunked-255 form for older DNS providers.
* Built-in test-email and DNS-record verification tools.
* Aligns the DKIM `d=` parameter with the `From:` header domain, so DMARC alignment passes.
* Does not double-sign. If another plugin (e.g. WP Mail SMTP) is already configured for DKIM, this plugin steps aside.
* RSA-2048 only for v1. Ed25519 (RFC 8463) is on the roadmap once receiver support broadens.

**What this plugin does NOT do (v1):**

* Sign mail that bypasses `wp_mail()` / PHPMailer (e.g. raw `mail()` calls, plugins that ship their own SMTP libraries).
* Multi-domain signing within a single WordPress site.
* Provide an automated key-rotation UI. Manual regenerate + republish is documented.

== Installation ==

1. Upload and activate the plugin.
2. Go to **Settings → WP Mail DKIM**.
3. Confirm the signing domain and selector (defaults are sensible).
4. Click "Generate key pair".
5. Copy the displayed DNS TXT record into your DNS provider.
6. Wait for DNS propagation (typically minutes; can be up to 24 hours).
7. Use the "Check DNS record" button to confirm the record is live.
8. Tick "Enabled" and save.
9. Use "Send test email" to verify signing works end-to-end.

== Frequently Asked Questions ==

= Why is my DKIM signature failing verification after going through my mail relay? =

Some MTAs (notably Amavis configured aggressively) rewrite the message body or headers in ways that break the signature. If you see `dkim=fail (body hash did not verify)` in the `Authentication-Results:` header, the relay is mutating the signed material. Either exclude the domain from body modification on the relay, or move signing onto the relay itself.

= My DNS TXT record is too long for my DNS provider. =

The settings page shows two forms of the record value: a single-string form and a chunked form (broken into quoted 255-character substrings). Most modern DNS providers handle the split transparently; if yours does not, paste the chunked form.

= What happens if I deactivate or delete the plugin? =

Deactivation leaves all data in place. Deletion (uninstall) removes the stored key and settings only if you have ticked the "Remove all data on uninstall" checkbox in the settings page first; otherwise data is preserved across reinstalls.

== Changelog ==

= 0.1.0 =
* Initial release.

== Upgrade Notice ==

= 0.1.0 =
Initial release.
