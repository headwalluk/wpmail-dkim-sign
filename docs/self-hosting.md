# Self-hosting

## Server requirements

| Requirement | Minimum | Notes |
|---|---|---|
| WordPress | 6.9 | |
| PHP | 8.2 | |
| `ext-openssl` | | Generates the key pair and reads it back. Enabled in almost every PHP build |
| Outbound DNS | | **Check DNS record** and zone detection use PHP's `dns_get_record()`, through the server's resolver |
| Outbound HTTPS to `api.github.com` | Optional | Used by the bundled updater. Turn it off with the [`wpdkm_updater_enabled`](developers/hooks-and-filters.md#wpdkm_updater_enabled) filter where outbound HTTPS isn't allowed |

## What the plugin stores

**Options:**

- `wpdkm_settings`: the settings, around 250 bytes, autoloaded
- `wpdkm_keypair`: the private key, the public key and the creation date, around 3 KB, not autoloaded

**Transients:**

| Transient | Lifetime | Holds |
|---|---|---|
| `wpdkm_capture_<user_id>` | 30 minutes | The last **Send test email** result for that user |
| `wpdkm_dns_check_<user_id>` | 30 minutes | The last **Check DNS record** result for that user |
| `wpdkm_zone_<md5 of domain>` | 24 hours | The detected DNS zone for the signing domain, or a marker that detection failed |
| `wpmail_dkim_sign_github_release` | 12 hours | The latest release found on GitHub |
| `wpmail_dkim_sign_github_failed` | 1 hour | Set after a failed GitHub lookup, to hold off retrying |

There are no custom tables, files, cron events or background processes. Uninstalling removes the options and the updater transients only if **Remove all plugin data** is ticked; see [Configuration](configuration.md#setup-tab-data-retention).

## Chroot OpenSSL configuration

If PHP-FPM runs the site in a `chroot`, OpenSSL inside PHP can't find its configuration file, and **Generate key pair** fails. The PHP error log shows lines like:

```
error:10000080:BIO routines::no such file
error:FFFFFFFF80000002:system library::No such file or directory
```

### Cause

On Debian, Ubuntu and their derivatives, OpenSSL is built with `OPENSSLDIR=/usr/lib/ssl`. On the host, `/usr/lib/ssl/openssl.cnf` is a symlink to `/etc/ssl/openssl.cnf`. Inside a chroot only the files you have copied in exist, so neither path resolves.

### Fix

Run as root, replacing `/var/www/example.com` with the chroot's root directory:

```bash
# Copy openssl.cnf into the chroot.
mkdir -p /var/www/example.com/etc/ssl
cp /etc/ssl/openssl.cnf /var/www/example.com/etc/ssl/openssl.cnf

# Add the symlink at the path OpenSSL was built to look in.
mkdir -p /var/www/example.com/usr/lib/ssl
ln -s /etc/ssl/openssl.cnf /var/www/example.com/usr/lib/ssl/openssl.cnf
```

The symlink's target is absolute, and inside the chroot the kernel resolves it relative to the chroot's root, so it lands on the copied file. No PHP-FPM restart is needed: OpenSSL looks for the file again on the next attempt.

Click **Generate key pair** to confirm. Success shows the *New key pair generated* notice.

### `/dev/urandom`

OpenSSL also needs `/dev/urandom` inside the chroot. Most chroot setups already provide it; check with:

```bash
ls -la /var/www/example.com/dev/urandom
```

If it's missing, the chroot needs `/dev` populated, usually with a bind mount in the PHP-FPM service unit. Your distribution's PHP-FPM chroot documentation covers the details.

### Scripting it for many sites

The fix is needed once per chroot. As a script for a provisioning system:

```bash
#!/bin/bash
# Stage the OpenSSL config for wpmail-dkim-sign inside a PHP-FPM chroot.
# Usage: ./stage-openssl.sh /var/www/example.com
set -euo pipefail

CHROOT_ROOT="${1:?chroot root required}"

install -d "${CHROOT_ROOT}/etc/ssl"
install -m 0644 /etc/ssl/openssl.cnf "${CHROOT_ROOT}/etc/ssl/openssl.cnf"

install -d "${CHROOT_ROOT}/usr/lib/ssl"
ln -sf /etc/ssl/openssl.cnf "${CHROOT_ROOT}/usr/lib/ssl/openssl.cnf"

if [ ! -e "${CHROOT_ROOT}/dev/urandom" ]; then
    echo "WARNING: /dev/urandom is missing from the chroot. Key generation will fail until /dev is populated." >&2
fi

echo "OpenSSL config staged in ${CHROOT_ROOT}."
```

## Performance

Signing is local and quick. PHPMailer hashes the body and the signed headers with SHA-256 and signs the result with the RSA-2048 key. With a 5 KB message body, that takes well under a millisecond on a modern server.

The plugin's own work is small next to that: a few comparisons in the pre-flight checks, and one read of the `wpdkm_keypair` option the first time a request signs a message. Later messages in the same request reuse the key already in memory.

No network calls happen while mail is being sent. The only outbound requests are **Check DNS record**, which you run by hand, the zone detection behind the DNS record panel (cached for 24 hours), and the update check, at most once every 12 hours on admin, cron and WP-CLI requests.
