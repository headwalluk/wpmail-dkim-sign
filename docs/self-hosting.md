# Self-hosting

## Server requirements

| Requirement | Minimum | Notes |
|---|---|---|
| WordPress | 6.0 | The plugin uses PHPMailer's DKIM properties, which have shipped natively in WP core since 5.5. 6.0 is set as the floor to ensure consistent behaviour. |
| PHP | 8.0 | Uses union types, nullable types, typed properties. |
| `ext-openssl` | — | Required for key generation and validation. Loaded by default in virtually all PHP distributions. |
| `dns_get_record` | — | Built into PHP — used for the Check DNS Record button. |
| Outbound HTTPS to `api.github.com` | optional | Required for the bundled GitHub auto-updater. Disable per-site via the `wpdkm_updater_enabled` filter if outbound HTTPS isn't permitted. |

Storage:

- Two `wp_options` rows: `wpdkm_settings` (autoload on, ~250 bytes) and `wpdkm_keypair` (autoload off, ~3 KB).
- Two short-lived transients used only during diagnostic operations: `wpdkm_capture_<user_id>` and `wpdkm_dns_check_<user_id>` (30 min TTL each).
- One long-lived zone-detection transient per signing domain: `wpdkm_zone_<md5>` (24h TTL).

## Chroot OpenSSL configuration

If your PHP-FPM pool is configured with `chroot`, the OpenSSL library inside PHP cannot find its config file by default, and key generation will fail with errors like:

```
error:10000080:BIO routines::no such file
error:FFFFFFFF80000002:system library::No such file or directory
```

### Cause

OpenSSL on Debian/Ubuntu (and derivatives) is compiled with `OPENSSLDIR=/usr/lib/ssl`. The config file actually lives at `/etc/ssl/openssl.cnf` — `/usr/lib/ssl/openssl.cnf` on the host is a symlink to it. Inside a chroot, only the explicitly-staged files are present, so neither path resolves.

### Fix

Run as root, replacing `/var/www/example.com` with your chroot root:

```bash
# Stage the openssl.cnf inside the chroot.
sudo mkdir -p /var/www/example.com/etc/ssl
sudo cp /etc/ssl/openssl.cnf /var/www/example.com/etc/ssl/openssl.cnf

# Add the symlink that OpenSSL's compiled-in path expects.
sudo mkdir -p /var/www/example.com/usr/lib/ssl
sudo ln -s /etc/ssl/openssl.cnf /var/www/example.com/usr/lib/ssl/openssl.cnf
```

The symlink target is an absolute path — when OpenSSL inside the chroot follows the link, the kernel resolves the path *relative to the chroot*, landing at the copied file. No FPM restart required; OpenSSL re-resolves on next key generation attempt.

Verify by clicking **Generate key pair** in the admin. Success looks like the no-error "New key pair generated" notice.

### `/dev/urandom` availability

OpenSSL needs `/dev/urandom` for entropy. Most chroot setups already mount or copy device nodes — verify with:

```bash
ls -la /var/www/example.com/dev/urandom
```

If missing, your FPM chroot setup needs `/dev` populated. The simplest approach on systemd-based systems is a bind mount in the FPM service unit; consult your distribution's PHP-FPM chroot documentation for specifics.

## Fleet automation

If you're rolling this out across multiple chrooted sites, the OpenSSL fix is a one-time-per-site configuration task. A small script for your provisioning system:

```bash
#!/bin/bash
# Stage OpenSSL config for wpmail-dkim-sign inside a PHP-FPM chroot.
# Usage: ./stage-openssl.sh /var/www/example.com
set -euo pipefail

CHROOT="${1:?chroot root required}"

install -d "$CHROOT/etc/ssl"
install -m 0644 /etc/ssl/openssl.cnf "$CHROOT/etc/ssl/openssl.cnf"

install -d "$CHROOT/usr/lib/ssl"
ln -sf /etc/ssl/openssl.cnf "$CHROOT/usr/lib/ssl/openssl.cnf"

ls -la "$CHROOT/dev/urandom" >/dev/null || {
    echo "WARNING: /dev/urandom not present in chroot — entropy will fail. Mount or copy /dev nodes." >&2
}

echo "OpenSSL config staged in $CHROOT."
```

## Performance characteristics

Signing adds approximately 1–5 ms per outbound email. The plugin's overhead is dominated by:

- Reading the keypair from `wp_options` on first sign call per request (subsequent calls in the same request hit a static cache — no further DB hits, no re-parsing of the PEM).
- PHPMailer's DKIM-Signature computation in `preSend()` — SHA-256 hashing of the body and selected headers, RSA-2048 signing of the resulting hash.

For a typical WP request that sends one to five mails (e.g. WooCommerce order completion), the total added latency is in the single-digit milliseconds and dominated by PHPMailer's signing cryptography, not by our plugin's work.

## What this plugin doesn't add to your hosting bill

- No external API calls during mail sending — signing is entirely local.
- No queue or background processes.
- No additional database tables (just two `wp_options` rows).
- The bundled GitHub updater makes at most one outbound HTTPS call to `api.github.com` per 12 hours per site (cached via transient). After a failed call it waits an hour before trying again. It only runs on admin, cron and WP-CLI requests, never on front-end page loads.
