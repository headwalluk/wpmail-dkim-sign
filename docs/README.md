# WP Mail DKIM Sign: documentation

DKIM signing for mail sent through `wp_mail()`, attached at the WordPress layer before the message reaches your upstream relay. The admin screens explain each step as you go; these pages cover the detail behind them.

## For site administrators

- [Installation walkthrough](installation.md): from install to a verified `dkim=pass` at a real mailbox
- [How it works](how-it-works.md): what gets signed, the pre-flight checks, canonicalisation, key storage, and what the plugin doesn't cover
- [Configuration](configuration.md): every setting, key management and rotation, data retention
- [Troubleshooting](troubleshooting.md): DNS mistakes, unsigned mail, body-rewriting relays, alignment

## For server administrators

- [Self-hosting](self-hosting.md): requirements, stored data, OpenSSL inside a PHP-FPM chroot, performance

## For developers

- [Hooks and filters](developers/hooks-and-filters.md): the public extension surface

## Reporting a problem

Open an issue on the [GitHub repository](https://github.com/headwalluk/wpmail-dkim-sign/issues). Include the **Pre-flight skip reason** from the Verify tab, or the `Authentication-Results:` header from the receiving mailbox, if you have one.
