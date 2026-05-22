<?php
/**
 * Private plugin functions.
 *
 * Lightweight helpers in the WPMail_DKIM_Sign namespace that are
 * too small to justify a dedicated class. Loaded eagerly from the
 * bootstrap so they are available to every sub-service from first
 * instantiation onwards.
 *
 * @package WPMail_DKIM_Sign
 */

namespace WPMail_DKIM_Sign;

defined( 'ABSPATH' ) || die();

/**
 * Return the bootstrapped plugin orchestrator instance.
 *
 * The canonical accessor used by sub-services to reach the
 * orchestrator and pull sibling sub-services via its lazy-loading
 * get_*() methods. The global variable itself is populated once
 * during bootstrap by wpdkm_run() in the plugin entry file;
 * nothing else should write to it.
 *
 * From inside the WPMail_DKIM_Sign namespace this resolves with
 * the unqualified call `get_plugin()` — no leading backslash, no
 * `use` statement needed.
 *
 * @since 0.1.0
 *
 * @return \WPMail_DKIM_Sign\Plugin The orchestrator instance.
 */
function get_plugin(): \WPMail_DKIM_Sign\Plugin {
	global $wpdkm_plugin;

	return $wpdkm_plugin;
}

/**
 * Detect the authoritative DNS zone for a given hostname.
 *
 * Walks from the most specific candidate to the least specific
 * (stripping one label at a time) and returns the first ancestor
 * for which an SOA record exists in the answer section. Result is
 * cached in a transient for 24 hours since zone boundaries rarely
 * change.
 *
 * Power users can short-circuit detection and force a specific zone
 * via the wpdkm_dns_zone filter, useful for sub-delegated subdomains
 * that don't have an SOA record reachable from the public resolver
 * the WP host uses.
 *
 * Returns null if no SOA is found at any ancestor (e.g. DNS lookups
 * failing in a chroot, or a non-existent domain). Callers should
 * fall back to displaying the full FQDN only.
 *
 * @since 0.1.0
 *
 * @param string $domain Domain to find the zone for.
 *
 * @return string|null Zone (apex domain), or null on failure.
 */
function compute_zone( string $domain ): ?string {
	/**
	 * Filter the auto-detected DNS zone before lookup.
	 *
	 * Return a non-empty string to bypass the SOA walk and use that
	 * zone directly. Return null to fall back to displaying only
	 * the full FQDN (no host-name shortcut).
	 *
	 * @since 0.1.0
	 *
	 * @param string|null $zone   Override zone, or null to auto-detect.
	 * @param string      $domain Domain being resolved.
	 */
	$override = apply_filters( 'wpdkm_dns_zone', null, $domain );

	$result = null;

	if ( is_string( $override ) && '' !== $override ) {
		$result = $override;
	} else {
		$cache_key = 'wpdkm_zone_' . md5( $domain );
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			// Cached result; the sentinel "__null__" stands in for a previous lookup failure.
			$result = ( '__null__' === $cached ) ? null : (string) $cached;
		} else {
			$result = compute_zone_via_soa_walk( $domain );
			set_transient( $cache_key, is_null( $result ) ? '__null__' : $result, DAY_IN_SECONDS );
		}
	}

	return $result;
}

/**
 * Perform the SOA walk that backs compute_zone.
 *
 * Split out for testability and to keep compute_zone's caching
 * layer separate from the lookup mechanics.
 *
 * @since 0.1.0
 *
 * @param string $domain Domain to walk.
 *
 * @return string|null
 */
function compute_zone_via_soa_walk( string $domain ): ?string {
	$result = null;
	$parts  = explode( '.', $domain );
	$count  = count( $parts );

	while ( $count >= 2 ) {
		$candidate = implode( '.', $parts );
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- dns_get_record warns on lookup failure; we handle empty results below.
		$records = @dns_get_record( $candidate, DNS_SOA );

		if ( is_array( $records ) && ! empty( $records ) ) {
			$result = $candidate;
			break;
		} else {
			// No SOA at this candidate — strip one label and try the parent.
			array_shift( $parts );
			--$count;
		}
	}

	return $result;
}

/**
 * Strip a zone suffix from a fully-qualified name to derive the host portion.
 *
 * Returns null when zone is missing, empty, or not actually a suffix of
 * the full name. Callers should treat null as "no host shortcut available"
 * and display only the FQDN.
 *
 * @since 0.1.0
 *
 * @param string      $full_name Fully-qualified DNS name.
 * @param string|null $zone      Apex zone, or null.
 *
 * @return string|null Host portion, or null if zone is absent / not a suffix.
 */
function derive_host_name( string $full_name, ?string $zone ): ?string {
	$result = null;

	if ( is_string( $zone ) && '' !== $zone ) {
		$suffix = '.' . $zone;
		if ( str_ends_with( $full_name, $suffix ) ) {
			$result = substr( $full_name, 0, -strlen( $suffix ) );
		} else {
			// Zone is not actually a suffix of the full name; treat as no shortcut.
		}
	} else {
		// No zone supplied.
	}

	return $result;
}
