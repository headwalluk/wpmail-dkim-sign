# Hooks and filters

The plugin exposes two filters as its public extension surface. There are no actions in v1.

Place customisation code in a must-use plugin or in your site-specific plugin — not in `functions.php`, which can get switched out by theme changes.

## `wpdkm_updater_enabled`

Controls whether the bundled GitHub auto-updater runs.

- **Default:** `true`
- **Returns:** `bool`

When this filter returns `false`, the plugin will not check GitHub for updates, will not inject itself into WordPress's `update_plugins` transient, and will not respond to the "View details" modal. The current installed version stays pinned in place until the filter is removed or returned `true` again.

Useful for:

- Staging or local-dev sites where you don't want WP to surface an update prompt for a plugin you're already managing via git/symlink.
- Sites with restrictive outbound firewall rules that block `api.github.com`.
- Temporary pinning when a specific version is known-good and you want to defer upgrading.

### Example — pin the current version on a staging site

```php
add_filter( 'wpdkm_updater_enabled', '__return_false' );
```

### Example — pin only on a specific environment

```php
add_filter(
    'wpdkm_updater_enabled',
    function ( $enabled ) {
        if ( defined( 'WP_ENVIRONMENT_TYPE' ) && 'staging' === WP_ENVIRONMENT_TYPE ) {
            return false;
        }
        return $enabled;
    }
);
```

## `wpdkm_dns_zone`

Overrides the auto-detected DNS zone for the configured signing domain. The zone is used to derive the "Host name (recommended)" shortcut on the Setup tab and to construct misconfiguration probes on the Verify tab.

- **Default:** `null` (auto-detect via SOA walk)
- **Returns:** `string|null` — return a non-empty string to bypass detection, or `null` to fall back to the auto-detect logic
- **Passed arguments:**
  - `string|null $zone` — the current value (null by default, or the previous filter's return)
  - `string $domain` — the configured signing domain being resolved

The auto-detect logic walks from most-specific to least-specific candidate (stripping one label at a time) and returns the first ancestor with an SOA record in the answer section. This works reliably for most setups but can fail on:

- Sub-delegated subdomains where the SOA isn't reachable from the WP host's resolver
- Multi-part TLDs where the apex isn't a single ancestor (the SOA walk handles this correctly, but if your resolver caches incorrectly the result may differ)
- Environments where outbound DNS to authoritative resolvers is blocked

### Example — force a specific zone

```php
add_filter(
    'wpdkm_dns_zone',
    function () {
        return 'example.com';
    }
);
```

### Example — override only for one domain

```php
add_filter(
    'wpdkm_dns_zone',
    function ( $zone, $domain ) {
        if ( 'tricky.example.com' === $domain ) {
            return 'tricky.example.com';
        }
        return $zone;
    },
    10,
    2
);
```

### Example — disable the host-name shortcut entirely

If you'd rather users only see the Full FQDN form (no Host-name shortcut), return an empty string or a value that isn't a suffix of the FQDN — the host-name derivation falls back to `null` and the Setup tab shows only the full record name.

```php
add_filter( 'wpdkm_dns_zone', '__return_empty_string' );
```

## Future hooks

If you have a specific extension point you'd like to see (e.g. a `wpdkm_pre_sign` filter for last-mile customisation of the DKIM headers, or an action when a key is generated/deleted), open an issue on the [GitHub repo](https://github.com/headwalluk/wpmail-dkim-sign/issues).
