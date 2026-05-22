# Installation walkthrough

This walks you through installing WP Mail DKIM Sign on a single WordPress site and confirming that mail leaves with a valid DKIM signature that verifies at the receiver.

## 1. Install and activate the plugin

1. Download `wpmail-dkim-sign.zip` from the [latest release](https://github.com/headwalluk/wpmail-dkim-sign/releases/latest).
2. In WordPress admin: **Plugins → Add New → Upload Plugin → choose the zip → Install Now → Activate**.
3. Navigate to **Settings → WP Mail DKIM**.

The plugin does nothing on activation — no key is generated, no signing happens until you complete the steps below. This is intentional.

## 2. Configure the signing domain

On the **Setup** tab, confirm the four fields:

| Field | Default | Notes |
|---|---|---|
| Signing domain | Site host with `www.` stripped | This is the `d=` value. Must match the From: header domain on outbound mail. |
| Selector | `website` | DNS label used as the subdomain in `<selector>._domainkey.<domain>`. Lowercase letters, digits, hyphens. |
| Canonicalisation | `relaxed/relaxed` | Tolerates whitespace and header changes a downstream relay may apply. Leave at default unless you have a reason. |
| Signing | Off | Leave off until the DNS record is published and verified. |

Click **Save settings**.

## 3. Generate the key pair

Click **Generate key pair**. The plugin generates a fresh RSA-2048 keypair and stores it in `wp_options` (autoload disabled, private key never touches disk).

Once generated, the DNS record block becomes visible.

## 4. Publish the DNS TXT record

This is where most mistakes happen — DNS provider UIs vary in how they want the record name. The plugin shows two forms:

**Host name (recommended)** — e.g. `website._domainkey.devx`
Paste this into the **Host**, **Name**, or **Subdomain** field at your DNS provider. The provider will automatically append your zone (e.g. `headwall.tech`) to produce the final record name `website._domainkey.devx.headwall.tech`.

**Full record name (FQDN)** — e.g. `website._domainkey.devx.headwall.tech`
Use this only if your DNS provider explicitly asks for the fully-qualified name. Most providers' Host fields will auto-append your zone, so pasting the FQDN here results in a doubled zone (`website._domainkey.devx.headwall.tech.headwall.tech`). See [Troubleshooting → Doubled zone](troubleshooting.md#doubled-zone) if you've done this by accident.

For the value, use the single-string form first — most modern DNS providers handle the length transparently. If your provider rejects it as too long, use the chunked-255 form instead.

| Field | Value |
|---|---|
| Type | `TXT` |
| Host | (from Host name above, or full record name if your provider asks for FQDN) |
| Value | `v=DKIM1; k=rsa; p=<base64>` (single string, or chunked quoted strings) |
| TTL | Provider default (typically 1 hour) is fine |

Save the record at your DNS provider.

## 5. Verify the record is live

Wait a few minutes for propagation, then on the **Verify** tab click **Check DNS Record**.

Possible results:

- **Match** — record published correctly, `p=` matches the locally-stored key. You're good to proceed.
- **No record found** — propagation not complete (wait), or the record wasn't published, or the Host field had a typo.
- **Misconfigured DNS — zone appended twice** — you pasted the FQDN into the Host field; edit it to use just the Host-name form.
- **Misconfigured DNS — missing `_domainkey` label** — the Host field is missing the `_domainkey` subdomain.
- **Mismatch** — record present but the `p=` value differs (typically a stale record from a previous key, or you regenerated locally and forgot to update DNS).

You can also verify from a shell:

```bash
dig +short TXT website._domainkey.example.com
```

## 6. Enable signing

Once **Check DNS Record** reports **Match**, return to the **Setup** tab, tick **Enabled — sign outbound mail with DKIM**, and click **Save settings**.

The plugin is now active.

## 7. Verify the result at a real receiver

The most reliable test is to send a message to an external mailbox you control (Gmail, outlook.com) and inspect the `Authentication-Results:` header at the receiver.

On the **Verify** tab, enter the test recipient address and click **Send test email**. The page captures the prepared headers locally so you can confirm a `DKIM-Signature:` line was attached.

Then open the received message at the receiver and look for the `Authentication-Results:` header. A successful Microsoft result looks like:

```
Authentication-Results: spf=pass (sender IP is 192.0.2.1)
                        smtp.mailfrom=example.com;
                        dkim=pass (signature was verified)
                        header.d=example.com;
                        dmarc=pass action=none
                        header.from=example.com;
                        compauth=pass reason=100
```

Key things to look for:

- `dkim=pass` (signature verified)
- `header.d=example.com` matches `header.from=example.com` (DMARC alignment)
- `dmarc=pass`
- At Microsoft: `compauth=pass reason=100` is the strongest signal

If any of these are off, see [Troubleshooting](troubleshooting.md).

## What this plugin deliberately doesn't do (v1)

- Sign mail sent by code paths that bypass `wp_mail()` (raw PHP `mail()`, plugins that ship their own SMTP libraries).
- Multi-domain signing within a single WordPress site (single key, single `d=`).
- Ed25519 keys (RFC 8463) — receiver support is still patchy.
- Automated key rotation — manual regenerate + republish is documented above.
