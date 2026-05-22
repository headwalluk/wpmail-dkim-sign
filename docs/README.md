# WP Mail DKIM Sign — Documentation

DKIM signing for WordPress mail. The plugin is self-documenting in the admin UI; these pages exist for deeper context, troubleshooting, and developer reference.

## For site owners

- [Installation walkthrough](installation.md) — step-by-step from install through DKIM verification at a real receiver
- [Troubleshooting](troubleshooting.md) — common DNS gotchas, body-rewriting MTAs, alignment issues

## For self-hosters and ops

- [Self-hosting](self-hosting.md) — server requirements, chroot OpenSSL configuration, performance characteristics

## For developers

- [Hooks and filters](developers/filters.md) — the public extension surface (two filters; no actions in v1)
