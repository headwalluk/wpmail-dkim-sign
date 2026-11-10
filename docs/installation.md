# Installation walkthrough

This takes a single WordPress site from installing the plugin to a confirmed `dkim=pass` at a real receiving mailbox. Allow for DNS propagation time in step 5.

## 1. Install and activate the plugin

1. Download `wpmail-dkim-sign.zip` from the [latest release](https://github.com/headwalluk/wpmail-dkim-sign/releases/latest)
2. In the WordPress admin, go to **Plugins → Add New → Upload Plugin**, choose the zip, then **Install Now** and **Activate**
3. Open **Settings → WP Mail DKIM**, or click **Settings** under the plugin on the Plugins screen

Activation changes nothing. No key is generated and nothing is signed until you finish the steps below. Later releases arrive as normal WordPress plugin updates.

## 2. Check the signing domain and selector

On the **Setup** tab, check the two fields that make up the DNS record name:

- **Signing domain** defaults to your site's host name without `www.`. It must be the domain your site sends mail from, as in the `From:` address
- **Selector** defaults to `website`. Keep the default unless you already publish a DKIM record under that name

Leave **Signing** unticked for now, and click **Save settings**. Every setting is described in [Configuration](configuration.md).

## 3. Generate the key pair

Click **Generate key pair**. The plugin creates an RSA-2048 key pair and stores it in the database. The private key never leaves the site, and is never written to a file.

The **DNS TXT record** panel appears below the key details.

## 4. Publish the DNS TXT record

DNS providers differ in how they want the record name entered, and this is where most mistakes happen. The panel gives the name in two forms.

**Host name (recommended)**, for example `website._domainkey.shop`. Paste this into the **Host**, **Name** or **Subdomain** field at your DNS provider. The provider adds your zone (`example.com`) to make the full record name, `website._domainkey.shop.example.com`.

**Full record name (FQDN)**, for example `website._domainkey.shop.example.com`. Use this only when your provider explicitly asks for the fully-qualified name. Pasted into a Host field that adds the zone itself, it produces a doubled zone, `website._domainkey.shop.example.com.example.com`. See [troubleshooting](troubleshooting.md#doubled-zone) if that happens.

The host name form only appears when the plugin can detect your DNS zone. If it can't, use the full record name and check what your provider expects.

For the record's value, try **Value (single string)** first. Most providers handle its length automatically. If yours rejects it as too long, use **Value (chunked form)** instead: both encode the same record.

| Field at your DNS provider | What to enter |
|---|---|
| Type | `TXT` |
| Host / Name | The host name, or the full record name if your provider asks for it |
| Value | `v=DKIM1; k=rsa; p=...`, copied from the panel |
| TTL | Your provider's default is fine |

Save the record.

## 5. Check the record is live

Give it a few minutes, then click **Check DNS record** on the **Verify** tab. The result is one of:

- **Match**: the record is published and its `p=` value matches the stored key. Carry on to step 6
- **No record found**: the record hasn't propagated yet, wasn't saved, or the name has a typo. Wait and check again
- **Misconfigured DNS — zone appended twice**: the full record name went into a field that adds the zone. Change it to the host name form
- **Misconfigured DNS — missing _domainkey label**: the name is missing `._domainkey.` between the selector and the domain
- **Mismatch**: a record exists but holds a different key, usually an old record or one copied before the key was regenerated
- **Record present but unparseable**: a TXT record exists but isn't a valid DKIM record. Check for a truncated or mangled value

Propagation usually takes minutes, but can take up to 24 hours. You can also check from a shell:

```bash
dig +short TXT website._domainkey.example.com
```

## 6. Turn on signing

When **Check DNS record** reports **Match**, go back to the **Setup** tab, tick **Enabled — sign outbound mail with DKIM**, and click **Save settings**.

From now on, mail sent through `wp_mail()` from the signing domain is signed.

## 7. Confirm at a real receiver

On the **Verify** tab, enter an address at an external mailbox you can read, such as Gmail or outlook.com, and click **Send test email**. The page shows the headers as they left WordPress, so you can confirm a `DKIM-Signature:` header was attached.

Then open the message at the receiver and find its `Authentication-Results:` header. A good result at Microsoft looks like this:

```
Authentication-Results: spf=pass (sender IP is 192.0.2.1)
                        smtp.mailfrom=example.com;
                        dkim=pass (signature was verified)
                        header.d=example.com;
                        dmarc=pass action=none
                        header.from=example.com;
                        compauth=pass reason=100
```

Check for:

- `dkim=pass`: the signature verified
- `header.d=` equal to `header.from=`: the signing domain lines up with the `From:` domain, which DMARC needs
- `dmarc=pass`
- At Microsoft, `compauth=pass reason=100`, its strongest authentication result

If any of these are missing, see [Troubleshooting](troubleshooting.md). For what the plugin covers and what it doesn't, see [How it works](how-it-works.md#what-the-plugin-doesnt-do).
