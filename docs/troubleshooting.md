# Troubleshooting

Start on the **Verify** tab. **Check DNS record** sorts DNS problems into the categories below, and **Send test email** shows whether a message was signed and, if not, why.

## DNS issues

### No record found

There is no TXT record at `<selector>._domainkey.<signing-domain>`. Either it hasn't propagated yet, it was never saved, or the name has a typo.

1. Wait a few minutes and check again. Propagation is the usual cause
2. From a shell, run `dig +short TXT <selector>._domainkey.<domain>`. If that returns nothing either, the record isn't live
3. Check that the name at your DNS provider is the **Host name (recommended)** value from the Setup tab, not the full record name

### Doubled zone

Verify tab result: **Misconfigured DNS — zone appended twice**.

The full record name (for example `website._domainkey.example.com`) was pasted into a Host field that adds the zone itself, giving `website._domainkey.example.com.example.com`.

1. Open the record at your DNS provider
2. Change the name to the **Host name (recommended)** value from the Setup tab: `website._domainkey` for a site at the zone apex, or `website._domainkey.shop` for a site at `shop.example.com`
3. Save, and run **Check DNS record** again

### Missing `_domainkey` label

Verify tab result: **Misconfigured DNS — missing _domainkey label**.

The record was published at just the selector (`website`), without the `_domainkey` part. The name must have `._domainkey.` between the selector and the domain. Copy the **Host name (recommended)** value exactly.

### Mismatch

Verify tab result: **Mismatch**. A DKIM record exists, but its `p=` value isn't the stored public key. Usually one of:

- The key was regenerated on the site and the DNS record wasn't updated
- An older DKIM record, from another plugin or a manual setup, is still published at that name
- The wrong value was pasted at the DNS provider

Copy **Value (single string)** from the Setup tab, replace the published value, and run **Check DNS record** again. **Show published vs. local key** on the Verify tab puts the two values side by side.

### Record present but unparseable

There is a TXT record at the name, but it doesn't contain a valid `v=DKIM1` record with a `p=` value. The value has usually been truncated or mangled while pasting. Copy it again from the Setup tab, or try **Value (chunked form)** if your provider limits string length.

### No local key

Verify tab result: **No local key**. There is no key on the site to compare against. Generate one on the Setup tab first.

## Mail goes out unsigned

**Send test email** shows *No DKIM-Signature in the prepared message* along with a **Pre-flight skip reason**. The reasons are listed in [How it works](how-it-works.md#pre-flight-checks). The common ones:

### `plugin disabled in settings`

Tick **Enabled — sign outbound mail with DKIM** on the Setup tab and save. Only do that once **Check DNS record** reports **Match**.

### `no usable key stored`

No key pair has been generated, or the stored key can't be read. Generate one on the Setup tab. If generation fails, see [key generation fails](#key-generation-fails).

### `From: domain othercompany.com does not match configured d=example.com`

The message's `From:` address is at a different domain from the signing domain. The plugin won't sign it, because a `d=` that doesn't match the `From:` domain fails DMARC alignment, which is worse than an unsigned message. The comparison is exact, so `mail.example.com` and `example.com` don't match.

Either set the signing domain to the domain the site actually sends from, or change the `From:` address in whatever is sending the mail (WordPress's settings, an SMTP plugin, a contact form plugin) to use the signing domain. The plugin signs for one domain only.

### `PHPMailer DKIM_domain already set — another plugin is signing`

Another plugin, often an SMTP plugin, has already set up DKIM signing, and this plugin steps aside. Turn off DKIM in one of them.

## Signature fails at the receiver

### `dkim=fail (body hash did not verify)` at the receiver

Something between the site and the receiver changed the message body after it was signed. Signatures use `relaxed/simple` canonicalisation (fixed by PHPMailer; see [how it works](how-it-works.md#canonicalisation)). Header changes such as folding or case are tolerated, but any change to the body breaks the body hash, even whitespace.

Common causes:

- Postfix with Amavis, or a similar content filter, adding disclaimers or footers, rewriting links, or inserting scan results into the body
- Commercial relays that wrap or alter content

To fix it:

1. Exclude the signing domain from any content-rewriting rules on the relay
2. For Amavis, exclude the domain from disclaimer and footer addition, and from any filter that changes the body
3. If the relay has to change the content, signing must happen after the relay, on the relay itself. This plugin can't help in that case

### `dkim=pass` but `dmarc=fail`

The signature verified, but its `header.d=` doesn't line up with `header.from=`. Compare the two in the `Authentication-Results:` header:

```
dkim=pass header.d=example.com
header.from=example.com
```

This plugin only signs when the two match exactly, so a mismatch means the passing signature came from somewhere else: usually a relay or mail service adding its own DKIM signature with its own domain. Look at the `DKIM-Signature:` headers on the received message. There may be more than one, and each one's `d=` names its signer. If the plugin's signature is missing, run **Send test email** to see the skip reason.

## Other issues

### Key generation fails

Clicking **Generate key pair** shows *Key generation failed*. The plugin logs the OpenSSL error to the PHP error log, whatever the `WP_DEBUG` setting, in lines starting `wpmail-dkim-sign:`.

- **OpenSSL can't find its configuration inside a PHP-FPM chroot.** This is the most common cause on managed hosting. See [Self-hosting](self-hosting.md#chroot-openssl-configuration)
- **The PHP `openssl` extension isn't loaded.** Check with `php -m | grep openssl`, and enable it in your PHP configuration

### The settings page shows a critical error

If the PHP `openssl` extension is missing altogether, the settings page fails with a fatal *Call to undefined function openssl_...* once a key has been stored, and so does clicking **Generate key pair**. Enable the extension. A missing configuration file inside a chroot doesn't cause this: it produces the *Key generation failed* notice instead.

### Mail lands in Microsoft's Junk folder despite `dkim=pass`

DKIM and DMARC alignment are necessary for inbox delivery, but not enough on their own. Microsoft also weighs:

- **Sending IP reputation.** A relay's IP needs a clean history, and new IPs need warming up
- **Content.** Spammy subject lines, suspicious links, and bulk mail without unsubscribe headers
- **Domain age and volume.** New domains and sudden bursts of mail get extra scrutiny

A DKIM pass means authentication is sorted. Deliverability is a separate problem, with its own tools, such as the delivery reports in the Microsoft 365 admin centre or a service like mail-tester.com.
