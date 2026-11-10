# Configuration

Everything lives under **Settings → WP Mail DKIM**, which is also linked as **Settings** under the plugin on the Plugins screen. The page has two tabs: **Setup** for configuration and keys, and **Verify** for diagnostics. Only users with the `manage_options` capability can open it.

The settings are stored together in one `wp_options` row, `wpdkm_settings`, under the field names shown below.

## Setup tab: configuration

| Setting page label | Field | Default | What it does |
|---|---|---|---|
| Signing domain | `domain` | The site's host name, with any leading `www.` removed | The DKIM `d=` value. It must equal the domain of the `From:` address on outbound mail, or the message goes out unsigned. See [how it works](how-it-works.md#pre-flight-checks) |
| Selector | `selector` | `website` | The DNS label in `<selector>._domainkey.<signing-domain>`. Lowercase letters, digits and hyphens, up to 63 characters |
| Signing | `enabled` | Off | Tick **Enabled — sign outbound mail with DKIM** once the DNS record is live and verified. With this off the plugin does nothing to outbound mail |
| Canonicalisation | none | `relaxed/simple` | Shown for reference only. PHPMailer fixes this value; see [how it works](how-it-works.md#canonicalisation) |

The signing domain is lowercased and has any `www.` removed when saved. If either the domain or the selector is invalid, nothing is saved and the page shows *Some inputs were not valid*.

Changing the signing domain or the selector changes the DNS record name. Publish a record at the new name before you save, or receivers won't find the key.

## Setup tab: key pair

| Button | What it does |
|---|---|
| **Generate key pair** | Creates a new RSA-2048 key pair. Shown only when no key is stored |
| **Regenerate key** | Replaces the stored key with a new one, after a confirmation prompt. The published DNS record stops matching at once |
| **Delete key** | Removes the stored key, after a confirmation prompt. Signing stops until a new key is generated |

Once a key exists, the **DNS TXT record** panel shows the record to publish: the host name, the full record name, and the value as a single string or split into chunks for DNS providers that limit string length. See the [installation walkthrough](installation.md#4-publish-the-dns-txt-record) for which to use.

### Rotating the key

The plugin holds one key at a time, and both a new key and a new selector take effect as soon as they are saved. Any mail signed before the matching DNS record is visible fails verification at the receiver. To rotate without that, turn signing off for the changeover:

1. On the Setup tab, untick **Signing** and save. Mail now goes out unsigned
2. Change **Selector** to a new value, for example `website2`, and save
3. Click **Regenerate key** and confirm
4. Publish the new TXT record shown, at the new selector's name
5. On the Verify tab, run **Check DNS record** until it reports **Match**
6. Tick **Signing** again and save
7. Leave the old selector's record in DNS for a few days, so mail already in transit still verifies, then delete it

Moving to a new selector is what lets the old record stay published during step 7. If you regenerate under the same selector instead, the new record replaces the old one, and any mail still in transit that was signed with the old key fails.

## Setup tab: data retention

| Setting page label | Field | Default | What it does |
|---|---|---|---|
| Remove all plugin data (settings and key pair) on uninstall | `remove_on_uninstall` | Off | When ticked, deleting the plugin from the Plugins screen removes `wpdkm_settings`, `wpdkm_keypair` and the update-check cache |

Deactivating the plugin never removes data. With the box unticked, deleting and reinstalling the plugin keeps the same key, so the published DNS record stays valid.

## Verify tab

| Tool | What it does |
|---|---|
| **Check DNS record** | Looks up `<selector>._domainkey.<signing-domain>` fresh, every time, and compares the published `p=` value with the stored public key. It also checks for the common publishing mistakes. Each result is explained in [troubleshooting](troubleshooting.md#dns-issues) |
| **Send test email** | Sends a short message from `wpdkm-test@<signing-domain>` to the address you enter (the site admin email by default). It shows the headers as they were prepared, after signing, and whether a `DKIM-Signature:` header was attached. If it wasn't, the **Pre-flight skip reason** says why |

Results are kept for 30 minutes, per user, so you can leave the page and come back to them.

The test email is sent even while signing is disabled. That shows the skip reason, `plugin disabled in settings`, which is a quick way to confirm that the rest of the setup is right.
