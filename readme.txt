=== WP Mail DKIM Sign ===
Contributors: headwalluk
Tags: dkim, email, deliverability, dmarc, smtp
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

DKIM-sign outbound wp_mail() messages at the WordPress layer, before they reach your upstream MTA. RSA-2048, single signing domain.

== Description ==

Since approximately late April 2026, Microsoft (Outlook.com, Hotmail.com, Live.com, and many Microsoft 365 tenants) has tightened authentication on inbound mail. Messages with valid SPF and DMARC but no DKIM signature are now frequently silently rejected or filed to Junk.

If your WordPress site sends transactional email through an SMTP relay that does not sign on your behalf, this plugin attaches a DKIM signature inside WordPress — before the message ever leaves the site — so it survives any onward relay and arrives at Microsoft (and elsewhere) properly authenticated.

**Key points:**

* Self-service key generation in the WP admin — no shell access required.
* Copy-paste DNS TXT record display, including a chunked form for DNS providers that limit string length.
* Built-in Send test email button (captures the prepared headers post-signing so you can see the DKIM-Signature without leaving the admin).
* Built-in Check DNS record button (fresh dns_get_record, compares published p= to the locally stored public key).
* Aligns the DKIM `d=` parameter with the `From:` header domain, so DMARC alignment passes.
* Does not double-sign. If another plugin (e.g. WP Mail SMTP) is already configured for DKIM, this plugin steps aside.
* RSA-2048 only for v1. Ed25519 (RFC 8463) is on the roadmap once receiver support broadens.

**What this plugin does NOT do (v1):**

* Sign mail that bypasses `wp_mail()` / PHPMailer (e.g. raw `mail()` calls, plugins that ship their own SMTP libraries).
* Multi-domain signing within a single WordPress site.
* Rotate keys automatically. The manual rotation steps are in docs/configuration.md.

== Installation ==

1. Upload and activate the plugin.
2. Go to **Settings → WP Mail DKIM**.
3. On the **Setup** tab, confirm the signing domain (defaults to your site host with any leading `www.` stripped) and the selector (defaults to `website`).
4. Click **Generate key pair**.
5. Copy the displayed DNS TXT record into your DNS provider — most providers accept the single-string form; a chunked quoted-strings form is also shown for older providers.
6. Wait for DNS propagation (typically minutes, occasionally up to 24 hours).
7. Switch to the **Verify** tab and click **Check DNS record** to confirm the published value matches the locally stored public key.
8. Tick **Enabled** in the configuration form and save.
9. Use **Send test email** on the Verify tab, ideally targeting an external mailbox you control (e.g. an outlook.com address), and inspect the `Authentication-Results:` header at the receiver for `dkim=pass`.

== Frequently Asked Questions ==

= Why is my DKIM signature failing verification after going through my mail relay? =

Some MTAs (notably Amavis configured aggressively) rewrite the message body or headers in ways that break the signature. If you see `dkim=fail (body hash did not verify)` in the `Authentication-Results:` header, the relay is mutating the signed material. Either exclude the domain from body modification on the relay, or move signing onto the relay itself.

= Can I choose the canonicalisation? =

No. PHPMailer, the library WordPress sends mail through, always signs with relaxed/simple canonicalisation, and has no option to change it. Version 1.0.0 showed a canonicalisation setting, but it never took effect; 1.1.0 removes it, and the Setup tab now shows the fixed value. With simple body canonicalisation, any change to the message body after signing breaks the signature, including whitespace changes.

= My DNS TXT record is too long for my DNS provider. =

The settings page shows two forms of the record value: a single-string form and a chunked form (broken into quoted 255-character substrings). Most modern DNS providers handle the split transparently; if yours does not, paste the chunked form.

= What happens if I deactivate or delete the plugin? =

Deactivation leaves all data in place. Deletion (uninstall) removes the stored key and settings only if you have ticked "Remove all plugin data (settings and key pair) on uninstall" on the Setup tab first; otherwise data is preserved across reinstalls.

= Key generation fails with an OpenSSL error — what's going on? =

If your PHP-FPM pool runs inside a chroot jail, OpenSSL needs to be able to find its config inside the jail. On Debian/Ubuntu the config lives at `/etc/ssl/openssl.cnf` but OpenSSL is compiled with `OPENSSLDIR=/usr/lib/ssl`, so it actually looks for `/usr/lib/ssl/openssl.cnf` (a symlink to the etc copy outside the jail). Inside the jail you need both: a copy of `/etc/ssl/openssl.cnf` and a `/usr/lib/ssl/openssl.cnf` symlink pointing to it. You will also need `/dev/urandom` accessible inside the jail (most chroot setups do this already).

== Changelog ==

= 1.1.0 =
* Removed: the Canonicalisation setting, which never took effect. PHPMailer always signs relaxed/simple, so signing is unchanged.
* Added: a Settings link under the plugin on the Plugins screen.
* Changed: the bundled GitHub updater now runs only on admin, cron and WP-CLI requests, and backs off after a failed check.
* Fixed: "Creation of dynamic property" deprecation notices on PHP 8.2+ for every signed message.
* Fixed: HTML entities such as &amp; in the test email subject.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.1.0 =
Removes the Canonicalisation setting, which never took effect; signatures are relaxed/simple as before. No DNS changes needed.

= 1.0.0 =
Initial release.
