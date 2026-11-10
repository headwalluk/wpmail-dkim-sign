# Troubleshooting

The **Verify** tab in the admin diagnoses most issues automatically. Run **Check DNS Record** first — it'll classify the problem into one of the categories below.

## DNS issues

### No record found (NXDOMAIN)

Cause: either the record wasn't published, propagation hasn't completed, or the Host field had a typo.

Steps:

1. Wait a few minutes and retry — propagation is the usual culprit.
2. Run `dig +short TXT <selector>._domainkey.<domain>` from a shell. If `dig` also returns nothing, the record isn't live.
3. Double-check the Host field at your DNS provider matches the "Host name (recommended)" value on the Setup tab — not the Full FQDN.

### Doubled zone

Verify tab status: **Misconfigured DNS — zone appended twice**.

Cause: you pasted the Full FQDN (e.g. `website._domainkey.example.com`) into your DNS provider's Host field. The provider then auto-appended your zone (`example.com`), producing the doubled-zone record `website._domainkey.example.com.example.com`.

Fix:

1. Open the existing record at your DNS provider.
2. Change the Host field to just the "Host name (recommended)" value from the Setup tab (e.g. `website._domainkey` for an apex zone, or `website._domainkey.subdomain` for a subdomain).
3. Save and re-run Check DNS Record.

### Missing `_domainkey` label

Verify tab status: **Misconfigured DNS — missing `_domainkey` label**.

Cause: the Host field at your DNS provider was set to just the selector (`website`) without the `_domainkey` subdomain.

Fix: the Host field must include `._domainkey.` between the selector and the domain. Use the Setup tab's "Host name (recommended)" value verbatim.

### Mismatch (record present, `p=` differs)

Cause: typically one of:

- You regenerated the key locally and didn't update the DNS record afterwards.
- A previous DKIM record (different plugin, manual config, etc.) is still published.
- The wrong public key was pasted into the DNS provider.

Fix: copy the value from the Setup tab (Value, single string form) and replace the published TXT record. Then re-run Check DNS Record.

The Verify tab's mismatch panel includes a "Show published vs. local key" details element so you can confirm by eye which key is which.

## Signing issues

### Key generation fails with an OpenSSL error

If clicking **Generate key pair** results in the **Key generation failed** notice, check the PHP error log for `wpmail-dkim-sign:` entries. Typical causes:

- **OpenSSL config not reachable inside a chroot** — most common on managed hosting. See [Self-hosting → Chroot OpenSSL configuration](self-hosting.md#chroot-openssl-configuration).
- **PHP `openssl` extension not loaded** — verify with `php -m | grep openssl`. Re-enable via your PHP configuration.

### `From:` domain mismatch — mail goes unsigned

If the **Send test email** result shows "No DKIM-Signature in the prepared message" with a skip reason like `From: domain noreply@othercompany.com does not match configured d=example.com`, the message is being sent with a From: that doesn't align with the configured signing domain.

This is intentional — signing with a misaligned `d=` would fail DMARC at the receiver, which is worse than leaving the message unsigned.

Fix options:

- Configure the plugin's signing domain to match what the site actually sends as (if you only send from one domain).
- Adjust the From: address being used by the site (check WP general settings, any SMTP plugin, contact form plugins, etc.) so it matches the configured signing domain.

### `dkim=fail (body hash did not verify)` at the receiver

Cause: your upstream MTA is modifying the message body after the plugin signs it. The DKIM body hash no longer matches.

Common offenders:

- Postfix + Amavis with content-modification rules (disclaimer footers, link rewriting, virus-scan headers injected into the body).
- Commercial mail relays that wrap or modify content.

Mitigations (in order of preference):

1. Exclude the signing domain from any content-rewriting rules on the relay. Signatures use `relaxed/simple` canonicalisation (fixed by PHPMailer): header whitespace and case changes are tolerated, but any change to the body, including whitespace, breaks the body hash.
2. For Amavis specifically, exclude the domain from disclaimer/footer addition and from any body-mangling content filters.
3. If the relay genuinely needs to modify content, signing must happen *after* the relay — in which case this plugin can't help you. The fix is per-domain DKIM on the relay itself.

### `dkim=pass` at Gmail but fail at Microsoft

Most likely cause: alignment. Microsoft is strictly checking that `header.d` (DKIM) matches `header.from` (From:). Gmail is more lenient.

Inspect the Microsoft `Authentication-Results:` header carefully:

```
dkim=pass header.d=example.com
header.from=example.com
```

The two domain values must match. If `header.from` is `notifications.example.com` but `header.d` is `example.com`, that's a subdomain mismatch — DMARC strict alignment would fail.

The plugin enforces this at sign time (the From-domain pre-flight check), so if you're seeing this, the configured signing domain is likely a parent of the actual From: domain. Set the signing domain to match the From: domain exactly.

## Other issues

### Mail still going to Microsoft Junk despite `dkim=pass`

DKIM + DMARC alignment is necessary but not sufficient for inbox delivery. Microsoft uses additional signals:

- **Sending IP reputation** — your relay's IP needs a clean reputation history. New IPs need a warm-up period.
- **Content patterns** — spammy subject lines, suspicious link patterns, missing unsubscribe headers on bulk mail.
- **Domain age and traffic patterns** — newly-registered domains and sudden volume bursts get extra scrutiny.

DKIM passing means *authentication* is satisfied; deliverability is a separate problem with its own diagnostic tools (delivery analytics in M365 admin, third-party tools like mail-tester.com).

### The plugin is enabled but mail is still unsigned

Check the Verify tab's **Send test email** result. The skip reason field will tell you exactly why a given message wasn't signed — most commonly:

- Plugin is disabled in settings (toggle on the Setup tab).
- No key stored locally (generate one).
- `From:` domain doesn't match the configured signing domain.
- Another plugin has already configured PHPMailer's DKIM properties (first-write-wins; this plugin steps aside).

### Activation works but the Settings page returns a fatal error

Almost always caused by the OpenSSL config issue inside a chroot. See [Self-hosting → Chroot OpenSSL configuration](self-hosting.md#chroot-openssl-configuration).
