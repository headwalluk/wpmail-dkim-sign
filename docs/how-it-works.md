# How WP Mail DKIM Sign works

## The short version

WordPress sends mail through PHPMailer, and PHPMailer can already produce a DKIM signature if it is given a domain, a selector and a private key. This plugin supplies those three things on every `wp_mail()` call, after checking that signing is safe. PHPMailer does the cryptography.

Signing happens on the site, before the message is handed to the local MTA or SMTP relay. The receiving server looks up the public key in DNS at `<selector>._domainkey.<signing-domain>` and checks the signature against it.

## Signing flow

```
wp_mail()
  └── phpmailer_init (priority 10)
        └── Signer::sign()
              ├── Signing enabled?                       no  → skip
              ├── Private key stored and readable?       no  → skip
              ├── PHPMailer DKIM_domain still empty?     no  → skip
              ├── From: domain equals signing domain?    no  → skip
              └── all passed → set DKIM_domain, DKIM_selector, DKIM_private_string
  └── PHPMailer::send() computes and attaches DKIM-Signature
```

A skipped message is still sent, just unsigned. The plugin never blocks mail.

## Pre-flight checks

Each check exists to avoid making things worse. When one fails, the reason is written to the PHP error log (only while `WP_DEBUG` is on) and shown on the Verify tab after a test send. These are the exact skip reasons:

| Skip reason | Why the plugin steps aside |
|---|---|
| `plugin disabled in settings` | The **Signing** box on the Setup tab is unticked. This is the default after installation |
| `no usable key stored` | No key pair has been generated, or the stored key no longer parses |
| `PHPMailer DKIM_domain already set — another plugin is signing` | Another plugin or an SMTP plugin has configured DKIM first. Two signers would conflict, so the first one wins |
| `From: domain <from> does not match configured d=<domain>` | Signing with a `d=` that doesn't match the `From:` domain fails DMARC alignment, which is worse than not signing |
| `could not parse From: domain` | The message has no usable `From:` address |

The `From:` comparison is exact and case-insensitive. `example.com` does not match `mail.example.com`, in either direction.

## What gets signed

PHPMailer (7.x, as bundled with WordPress) signs with `a=rsa-sha256` and covers whichever of these headers the message has: `From`, `To`, `Cc`, `Date`, `Subject`, `Reply-To`, `Message-ID`, `Content-Type`, `MIME-Version` and `X-Mailer`. The body is covered by the body hash (`bh=`).

### Canonicalisation

Every signature uses `c=relaxed/simple`. PHPMailer hardcodes this and offers no way to change it, so the plugin has no setting for it.

- **Headers (relaxed):** changes to header case, folding and whitespace along the way don't break the signature
- **Body (simple):** any change to the body after signing breaks the signature, including whitespace changes, an added footer or a rewritten link

Version 1.0.0 offered a canonicalisation dropdown, but the choice was never applied: every 1.0.0 signature was already `relaxed/simple`. See [Troubleshooting](troubleshooting.md#dkimfail-body-hash-did-not-verify-at-the-receiver) if a relay alters message bodies.

## Key storage

- One RSA-2048 key pair per site, generated on the server with PHP's OpenSSL extension
- Stored in the `wpdkm_keypair` option with autoload off, so it is read only when it is needed, not on every page load
- Handed to PHPMailer as an in-memory PEM string. The private key is never written to a file
- Loaded at most once per request, then held in memory for any further mail in the same request

Anyone with access to the database can read the private key, as with any other secret WordPress stores in `wp_options`. Database backups contain it too.

## What the plugin doesn't do

- **Sign mail that bypasses `wp_mail()`.** Raw PHP `mail()` calls, and plugins that ship their own mail libraries, aren't signed
- **Sign for more than one domain.** One key, one `d=` per site. Mail from any other `From:` domain goes out unsigned
- **Ed25519 keys** (RFC 8463). Receiver support is still patchy
- **Rotate keys automatically.** Rotation is a manual regenerate and republish; see [Configuration](configuration.md#rotating-the-key)
- **Configure SPF or DMARC.** Those are DNS records you publish separately. DKIM alone doesn't give you a DMARC pass unless the domains align
