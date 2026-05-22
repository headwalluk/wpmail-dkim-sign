<?php
/**
 * GitHub auto-updater.
 *
 * Hooks the WordPress plugin update system to check a configured
 * GitHub repository for new releases and serves them as standard
 * plugin updates. Adapted from the same pattern used by quick-2fa.
 *
 * Adopts our house style: namespaced, lazy-loaded via Plugin
 * orchestrator, hooks wired in Plugin::run(), SESE on all methods,
 * and uses the wpdkm_ prefix for filters and transients per D12.
 *
 * @package WPMail_DKIM_Sign
 */

namespace WPMail_DKIM_Sign;

defined( 'ABSPATH' ) || die();

/**
 * Github_Updater
 *
 * Checks GitHub Releases for plugin updates and hooks into the
 * WordPress plugin update system.
 *
 * @since 1.0.0
 */
class Github_Updater {

	/**
	 * Plugin basename (e.g. "wpmail-dkim-sign/wpmail-dkim-sign.php").
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	private string $plugin_basename;

	/**
	 * Plugin slug (directory name).
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	private string $plugin_slug;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->plugin_basename = WPDKM_BASENAME;
		$this->plugin_slug     = dirname( $this->plugin_basename );
	}

	/**
	 * Check whether GitHub auto-updates are enabled.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	private function is_enabled(): bool {
		/**
		 * Filter whether GitHub auto-updates are enabled.
		 *
		 * Return false to disable update checks — useful for staging
		 * environments, local development, or temporarily pinning the
		 * plugin to its current version.
		 *
		 * @since 1.0.0
		 *
		 * @param bool $enabled Whether auto-updates are enabled. Default true.
		 */
		return (bool) apply_filters( 'wpdkm_updater_enabled', true );
	}

	/**
	 * Check GitHub for a newer release and inject into the update transient.
	 *
	 * @since 1.0.0
	 *
	 * @param object $transient The update_plugins transient object.
	 *
	 * @return object
	 */
	public function check_for_update( $transient ) {
		$checked = is_object( $transient ) && property_exists( $transient, 'checked' )
			? $transient->checked
			: false;

		if ( empty( $checked ) ) {
			// Early transient pass — WordPress hasn't populated its checked list yet.
			$this->log( 'check_for_update: transient has no checked list, skipping.' );
		} elseif ( ! $this->is_enabled() ) {
			$this->log( 'check_for_update: updates disabled via filter, skipping.' );
		} else {
			$release = $this->get_latest_release();

			if ( ! is_array( $release ) ) {
				$this->log( 'check_for_update: no release data returned from GitHub.' );
			} elseif ( version_compare( WPDKM_VERSION, $release['version'], '>=' ) ) {
				$this->log( 'check_for_update: current version ' . WPDKM_VERSION . ' is up to date (latest: ' . $release['version'] . ').' );
			} else {
				$this->log( 'check_for_update: update available ' . WPDKM_VERSION . ' → ' . $release['version'] . '.' );
				$transient->response[ $this->plugin_basename ] = (object) array(
					'slug'        => $this->plugin_slug,
					'plugin'      => $this->plugin_basename,
					'new_version' => $release['version'],
					'url'         => $release['html_url'],
					'package'     => $release['zip_url'],
				);
			}
		}

		return $transient;
	}

	/**
	 * Provide plugin information for the "View details" modal.
	 *
	 * @since 1.0.0
	 *
	 * @param false|object|array $result The result object or array. Default false.
	 * @param string             $action The API action being performed.
	 * @param object             $args   Plugin API arguments.
	 *
	 * @return false|object|array
	 */
	public function plugin_info( $result, $action, $args ) {
		$is_relevant = 'plugin_information' === $action
			&& ( $args->slug ?? '' ) === $this->plugin_slug
			&& $this->is_enabled();

		if ( ! $is_relevant ) {
			// Not our plugin or not the right action — return original unchanged.
		} else {
			$release = $this->get_latest_release();

			if ( ! is_array( $release ) ) {
				// No release data — return whatever WP had (typically false).
			} else {
				if ( ! function_exists( 'get_plugin_data' ) ) {
					require_once ABSPATH . 'wp-admin/includes/plugin.php';
				} else {
					// get_plugin_data already loaded.
				}

				$plugin_data = get_plugin_data( WPDKM_FILE, false, true );

				$info                = new \stdClass();
				$info->name          = $plugin_data['Name'] ?? $this->plugin_slug;
				$info->slug          = $this->plugin_slug;
				$info->version       = $release['version'];
				$info->author        = $plugin_data['AuthorName'] ?? '';
				$info->homepage      = $plugin_data['PluginURI'] ?? $release['html_url'];
				$info->requires      = $plugin_data['RequiresWP'] ?? '';
				$info->requires_php  = $plugin_data['RequiresPHP'] ?? '';
				$info->downloaded    = 0;
				$info->last_updated  = $release['published_at'] ?? '';
				$info->download_link = $release['zip_url'];

				if ( ! empty( $release['body'] ) ) {
					$info->sections = array(
						'description' => $plugin_data['Description'] ?? '',
						'changelog'   => wp_kses_post( wpautop( $release['body'] ) ),
					);
				} else {
					// No release body — leave sections unset.
				}

				$result = $info;
			}
		}

		return $result;
	}

	/**
	 * Clear cached release data after a plugin update completes.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Upgrader $upgrader The upgrader instance.
	 * @param array        $options  Update details.
	 *
	 * @return void
	 */
	public function clear_cache( $upgrader, $options ): void {
		unset( $upgrader );

		if (
			'update' === ( $options['action'] ?? '' )
			&& 'plugin' === ( $options['type'] ?? '' )
			&& ! empty( $options['plugins'] )
			&& in_array( $this->plugin_basename, $options['plugins'], true )
		) {
			delete_transient( UPDATER_CACHE_KEY );
			delete_site_transient( 'update_plugins' );
		} else {
			// Not our plugin being updated — leave cache alone.
		}
	}

	/**
	 * Fetch the latest release from GitHub, with transient caching.
	 *
	 * @since 1.0.0
	 *
	 * @return array|null Release data array, or null on failure.
	 */
	private function get_latest_release(): ?array {
		$release = null;
		$cached  = get_transient( UPDATER_CACHE_KEY );

		if ( is_array( $cached ) ) {
			$this->log( 'get_latest_release: using cached release data.' );
			$release = $cached;
		} else {
			$url      = sprintf( 'https://api.github.com/repos/%s/releases/latest', UPDATER_GITHUB_REPO );
			$response = wp_remote_get(
				$url,
				array(
					'timeout' => 10,
					'headers' => array(
						'Accept' => 'application/vnd.github.v3+json',
					),
				)
			);

			if ( is_wp_error( $response ) ) {
				$this->log_error( 'get_latest_release: HTTP request to ' . $url . ' failed — ' . $response->get_error_message() );
			} elseif ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
				$this->log_error( 'get_latest_release: GitHub returned HTTP ' . wp_remote_retrieve_response_code( $response ) . ' for ' . $url . '.' );
			} else {
				$body = json_decode( wp_remote_retrieve_body( $response ), true );

				if ( ! is_array( $body ) || empty( $body['tag_name'] ) ) {
					$this->log_error( 'get_latest_release: response JSON from ' . $url . ' missing tag_name.' );
				} else {
					$zip_url = $this->find_zip_asset( $body );

					if ( '' === $zip_url ) {
						$this->log_error( 'get_latest_release: no matching .zip asset for tag ' . $body['tag_name'] . '.' );
					} else {
						$this->log( 'get_latest_release: found release ' . $body['tag_name'] . '.' );

						$release = array(
							'version'      => ltrim( $body['tag_name'], 'v' ),
							'zip_url'      => $zip_url,
							'html_url'     => $body['html_url'] ?? '',
							'body'         => $body['body'] ?? '',
							'published_at' => $body['published_at'] ?? '',
						);

						set_transient( UPDATER_CACHE_KEY, $release, UPDATER_CACHE_TTL );
					}
				}
			}
		}

		return $release;
	}

	/**
	 * Find the plugin ZIP asset attached to a GitHub release.
	 *
	 * Looks for a .zip asset whose name matches the plugin slug
	 * (e.g. "wpmail-dkim-sign.zip" or "wpmail-dkim-sign-1.0.0.zip").
	 * Prefers the stable "{slug}.zip" name over a versioned match.
	 *
	 * @since 1.0.0
	 *
	 * @param array $release_data Decoded GitHub release API response.
	 *
	 * @return string Download URL, or empty string if no suitable asset found.
	 */
	private function find_zip_asset( array $release_data ): string {
		$zip_url = '';

		if ( empty( $release_data['assets'] ) || ! is_array( $release_data['assets'] ) ) {
			// No release assets to inspect.
		} else {
			$stable_name = $this->plugin_slug . '.zip';

			foreach ( $release_data['assets'] as $asset ) {
				$name = (string) ( $asset['name'] ?? '' );

				if ( $stable_name === $name ) {
					$zip_url = (string) ( $asset['browser_download_url'] ?? '' );
					break;
				} elseif ( '' === $zip_url
					&& str_starts_with( $name, $this->plugin_slug )
					&& str_ends_with( $name, '.zip' )
				) {
					// Accept any zip starting with the plugin slug as a fallback.
					$zip_url = (string) ( $asset['browser_download_url'] ?? '' );
				} else {
					// Asset doesn't match — keep iterating.
				}
			}
		}

		return $zip_url;
	}

	/**
	 * Debug-level log entry, gated on WP_DEBUG.
	 *
	 * Used for routine flow tracing — cache hits, version comparisons,
	 * "up to date" results. Hard failures use log_error.
	 *
	 * @since 1.0.0
	 *
	 * @param string $message Message to log.
	 *
	 * @return void
	 */
	private function log( string $message ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional debug logging.
			error_log( 'wpmail-dkim-sign Github_Updater: ' . $message );
		} else {
			// WP_DEBUG off — stay silent.
		}
	}

	/**
	 * Unconditional error log entry.
	 *
	 * Used for genuine failure conditions (HTTP errors, malformed
	 * responses, missing release assets) that should always be visible
	 * to a sysadmin diagnosing why updates aren't flowing — without
	 * requiring WP_DEBUG.
	 *
	 * @since 1.0.0
	 *
	 * @param string $message Message to log.
	 *
	 * @return void
	 */
	private function log_error( string $message ): void {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional error logging for updater failures.
		error_log( 'wpmail-dkim-sign Github_Updater [error]: ' . $message );
	}
}
