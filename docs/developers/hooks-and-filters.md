# Hooks and filters

This is the **public extension surface** of WP Mail DKIM Sign: two filters. There are no actions. Anything not listed here is internal and may change between releases, including the functions and classes in the `WPMail_DKIM_Sign` namespace and the structure of the stored options.

Put customisation code in a must-use plugin or a site-specific plugin rather than a theme's `functions.php`, so a theme change doesn't remove it.

## Compatibility

- A filter's name and arguments don't change within a major version. New arguments are only ever added at the end
- A renamed filter keeps working under its old name until at least the next major version, with a deprecation notice while `WP_DEBUG` is on
- Any change that could break an integration is listed in `CHANGELOG.md`

## `wpdkm_updater_enabled`

Whether the bundled GitHub updater checks for new releases.

**Parameters:**
- `bool $enabled`: `true` by default

**Returns:** `bool`. Values such as `'0'`, `'no'` and `'off'` are read as `false`.

When the filter returns `false`, the plugin doesn't contact GitHub, doesn't add itself to WordPress's list of available updates, and doesn't answer the **View details** request on the Plugins screen. The installed version stays as it is until the filter allows updates again. Other plugins are unaffected.

Useful for:

- Staging and development sites, where the plugin is deployed from git
- Servers whose firewall blocks outbound HTTPS to `api.github.com`
- Holding a site on a known-good version for a while

### Example: turn off update checks

```php
add_filter( 'wpdkm_updater_enabled', '__return_false' );
```

### Example: turn them off on staging only

```php
/**
 * Disable WP Mail DKIM Sign update checks on staging sites.
 *
 * @param bool $enabled Whether update checks are enabled.
 * @return bool
 */
function my_site_wpdkm_updates_off_on_staging( $enabled ) {
	if ( 'staging' === wp_get_environment_type() ) {
		$enabled = false;
	}

	return $enabled;
}
add_filter( 'wpdkm_updater_enabled', 'my_site_wpdkm_updates_off_on_staging' );
```

## `wpdkm_dns_zone`

Overrides the automatically detected DNS zone for the signing domain. The zone is used to work out the **Host name (recommended)** form of the record name on the Setup tab, and to look for the doubled-zone and missing-label mistakes on the Verify tab.

**Parameters:**
- `string|null $zone`: `null` by default, or the value from an earlier callback
- `string $domain`: the signing domain being looked up

**Returns:** `string|null`. A non-empty string is used as the zone, and detection is skipped. `null` or an empty string lets detection run as normal.

Detection walks up from the signing domain one label at a time, and takes the first name with an SOA record as the zone. The result is cached for 24 hours. Detection can go wrong when:

- A subdomain is delegated to separate nameservers, and its SOA isn't visible from the server's resolver
- The server's resolver is blocked from making the lookups, as in some chroot setups
- The resolver returns stale or incorrect answers

When detection finds nothing, the Setup tab shows only the full record name.

### Example: set the zone

```php
/**
 * Use example.com as the DNS zone for DKIM record names.
 *
 * @return string
 */
function my_site_wpdkm_zone() {
	return 'example.com';
}
add_filter( 'wpdkm_dns_zone', 'my_site_wpdkm_zone' );
```

### Example: set it for one domain only

```php
/**
 * Treat shop.example.com as its own DNS zone; leave other domains to detection.
 *
 * @param string|null $zone   Zone from an earlier callback, or null.
 * @param string      $domain Signing domain being looked up.
 * @return string|null
 */
function my_site_wpdkm_zone_for_shop( $zone, $domain ) {
	if ( 'shop.example.com' === $domain ) {
		$zone = 'shop.example.com';
	}

	return $zone;
}
add_filter( 'wpdkm_dns_zone', 'my_site_wpdkm_zone_for_shop', 10, 2 );
```

### Example: hide the host name form

To show only the full record name, return a zone that the record name doesn't end with. Returning an empty string won't work, because that lets detection run.

```php
/**
 * Hide the host name form of the DKIM record name.
 *
 * @return string
 */
function my_site_wpdkm_no_host_name() {
	return 'invalid';
}
add_filter( 'wpdkm_dns_zone', 'my_site_wpdkm_no_host_name' );
```

`invalid` is a top-level domain reserved by RFC 2606 that never appears in a real record name. The Verify tab's doubled-zone check uses the same zone, so it can't spot that mistake while this filter is in place. The missing-label check doesn't use the zone and still works.

## Requesting a hook

If you need an extension point that isn't here, such as a filter on the DKIM settings just before signing, or an action when a key is generated or deleted, open an issue on the [GitHub repository](https://github.com/headwalluk/wpmail-dkim-sign/issues).
