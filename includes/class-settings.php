<?php
/**
 * Settings sub-service.
 *
 * Owns the admin settings page, the configuration options row, and
 * form-submission handlers (save, generate / regenerate / delete key).
 *
 * @package WPMail_DKIM_Sign
 */

namespace WPMail_DKIM_Sign;

defined( 'ABSPATH' ) || die();

/**
 * Settings
 *
 * Settings persistence + the admin UI shell. Templates live under
 * admin-templates/ and are loaded via render_page().
 *
 * @since 1.0.0
 */
class Settings {

	/**
	 * Request-scoped cache of the loaded + defaults-merged settings.
	 *
	 * @since 1.0.0
	 *
	 * @var array<string, mixed>|null
	 */
	private static ?array $cache = null;

	/**
	 * Register the admin settings menu entry.
	 *
	 * Lives under Settings → WP Mail DKIM. Sub-menu placement keeps
	 * the top-level menu uncluttered — this is a configure-once
	 * plugin.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_menu(): void {
		add_options_page(
			__( 'WP Mail DKIM', 'wpmail-dkim-sign' ),
			__( 'WP Mail DKIM', 'wpmail-dkim-sign' ),
			CAP_MANAGE,
			MENU_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Append a Settings link to the plugin's row actions on the Plugins screen.
	 *
	 * @since 1.1.0
	 *
	 * @param mixed $links Row action links, keyed by action name.
	 *
	 * @return mixed The links with Settings appended, or the input unchanged if it is not an array.
	 */
	public function add_settings_action_link( mixed $links ): mixed {
		if ( is_array( $links ) && current_user_can( CAP_MANAGE ) ) {
			$links['settings'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( get_settings_url() ),
				esc_html_x( 'Settings', 'plugin row action link', 'wpmail-dkim-sign' )
			);
		} else {
			// Not an array from an earlier filter, or the user can't open the page.
		}

		return $links;
	}

	/**
	 * Enqueue admin CSS and JS, only on our settings page.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $hook_suffix Current admin page hook suffix.
	 *
	 * @return void
	 */
	public function enqueue_admin_assets( mixed $hook_suffix ): void {
		if ( 'settings_page_' . MENU_SLUG !== $hook_suffix ) {
			// Not our page — skip.
		} else {
			wp_enqueue_style(
				'wpdkm-admin',
				WPDKM_URL . 'assets/admin/admin.css',
				array(),
				WPDKM_VERSION
			);

			wp_enqueue_script(
				'wpdkm-admin',
				WPDKM_URL . 'assets/admin/admin.js',
				array(),
				WPDKM_VERSION,
				true
			);
		}
	}

	/**
	 * Render the settings page.
	 *
	 * Prepares a single $data array of escaped-on-output strings,
	 * action / nonce names, and pre-loaded model values, then hands
	 * off to the template under admin-templates/.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'wpmail-dkim-sign' ) );
		} else {
			$data = $this->build_page_data();
			require WPDKM_PATH . 'admin-templates/settings-page.php';
		}
	}

	/**
	 * Build the data array consumed by the settings page template.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	private function build_page_data(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only query parameter for notice rendering; not state-changing.
		$notice = isset( $_GET[ NOTICE_QUERY_PARAM ] )
			? sanitize_key( wp_unslash( $_GET[ NOTICE_QUERY_PARAM ] ) )
			: '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$settings = self::load();
		$verifier = get_plugin()->get_verifier();

		return array(
			'page_title'       => __( 'WP Mail DKIM', 'wpmail-dkim-sign' ),
			'settings'         => $settings,
			'keypair_meta'     => $this->get_keypair_meta(),
			'dns_record'       => $this->get_dns_record( $settings ),
			'canonicalisation' => PHPMAILER_CANONICALISATION,
			'notice'           => $notice,
			'admin_post'       => admin_url( 'admin-post.php' ),
			'admin_email'      => (string) get_option( 'admin_email', '' ),
			'test_result'      => $verifier->get_test_result(),
			'dns_check_result' => $verifier->get_dns_check_result(),
			'actions'          => array(
				'save'           => ACTION_SAVE,
				'generate_key'   => ACTION_GENERATE_KEY,
				'regenerate_key' => ACTION_REGENERATE_KEY,
				'delete_key'     => ACTION_DELETE_KEY,
				'test_email'     => ACTION_TEST_EMAIL,
				'check_dns'      => ACTION_CHECK_DNS,
			),
			'nonces'           => array(
				'save'           => NONCE_SAVE,
				'generate_key'   => NONCE_GENERATE_KEY,
				'regenerate_key' => NONCE_REGENERATE_KEY,
				'delete_key'     => NONCE_DELETE_KEY,
				'test_email'     => NONCE_TEST_EMAIL,
				'check_dns'      => NONCE_CHECK_DNS,
			),
		);
	}

	/**
	 * Build a short summary of the stored keypair for the UI.
	 *
	 * Returns null if no key is stored. The returned array
	 * deliberately excludes the private PEM — admin templates have
	 * no need for it, and reducing what reaches the rendering layer
	 * reduces the surface area for accidental disclosure.
	 *
	 * @since 1.0.0
	 *
	 * @return array{public_b64: string, created_at: string}|null
	 */
	private function get_keypair_meta(): ?array {
		$loaded = get_plugin()->get_keypair()->load();
		$result = null;

		if ( is_null( $loaded ) ) {
			// No key stored — UI will show the "generate" prompt instead.
		} else {
			$result = array(
				'public_b64' => $loaded['public_b64'],
				'created_at' => $loaded['created_at'],
			);
		}

		return $result;
	}

	/**
	 * Build the DNS-record display array, or null when no key is stored.
	 *
	 * Format provided by Keypair::format_txt_record using the currently
	 * saved selector and domain. If the saved selector/domain has
	 * changed since the user last published DNS, that mismatch is
	 * surfaced by the M6 "Check DNS record" button — this method
	 * always reflects what *should* be published right now.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $settings Current settings row (merged with defaults).
	 *
	 * @return array{name: string, value_full: string, value_chunked: string}|null
	 */
	private function get_dns_record( array $settings ): ?array {
		$domain = (string) $settings['domain'];
		$zone   = compute_zone( $domain );

		return get_plugin()->get_keypair()->format_txt_record(
			(string) $settings['selector'],
			$domain,
			$zone
		);
	}

	/**
	 * Handle a settings-form submission.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function handle_save(): void {
		check_admin_referer( ACTION_SAVE, NONCE_SAVE );

		if ( ! current_user_can( CAP_MANAGE ) ) {
			wp_die(
				esc_html__( 'You do not have permission to perform this action.', 'wpmail-dkim-sign' ),
				'',
				array( 'response' => 403 )
			);
		} else {
			$incoming = self::collect_form_input();
			$clean    = self::sanitize_settings( $incoming );

			if ( is_null( $clean ) ) {
				$this->redirect_with_notice( NOTICE_INVALID );
			} else {
				$existing = self::load();
				$merged   = array_merge( $existing, $clean );

				update_option( OPT_SETTINGS, $merged, true );
				self::$cache = $merged;

				$this->redirect_with_notice( NOTICE_SAVED );
			}
		}
	}

	/**
	 * Handle the "Generate key" form submission.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function handle_generate_key(): void {
		check_admin_referer( ACTION_GENERATE_KEY, NONCE_GENERATE_KEY );
		$this->require_manage_capability();

		$ok = get_plugin()->get_keypair()->generate();
		$this->redirect_with_notice( $ok ? NOTICE_KEY_GENERATED : NOTICE_KEY_ERROR );
	}

	/**
	 * Handle the "Regenerate key" form submission.
	 *
	 * Same generator path as handle_generate_key; the regenerate
	 * action exists separately so that JS confirm() can be wired
	 * specifically to the regenerate button (where overwriting an
	 * existing key has real consequences — DNS publication needs
	 * to follow).
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function handle_regenerate_key(): void {
		check_admin_referer( ACTION_REGENERATE_KEY, NONCE_REGENERATE_KEY );
		$this->require_manage_capability();

		$ok = get_plugin()->get_keypair()->generate();
		$this->redirect_with_notice( $ok ? NOTICE_KEY_REPLACED : NOTICE_KEY_ERROR );
	}

	/**
	 * Handle the "Delete key" form submission.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function handle_delete_key(): void {
		check_admin_referer( ACTION_DELETE_KEY, NONCE_DELETE_KEY );
		$this->require_manage_capability();

		get_plugin()->get_keypair()->delete();
		$this->redirect_with_notice( NOTICE_KEY_DELETED );
	}

	/**
	 * Read a settings value, falling back to the configured default.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key     Field name (domain, selector, enabled, remove_on_uninstall).
	 * @param mixed  $default_value Fallback if neither stored value nor a typed default exists.
	 *
	 * @return mixed
	 */
	public function get( string $key, mixed $default_value = null ): mixed {
		$settings = self::load();
		return $settings[ $key ] ?? $default_value;
	}

	/**
	 * Load settings from wp_options, merge with computed defaults, cache.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	private static function load(): array {
		if ( ! is_null( self::$cache ) ) {
			// Use cached value populated by an earlier call in this request.
		} else {
			$stored      = get_option( OPT_SETTINGS, array() );
			$stored      = is_array( $stored ) ? $stored : array();
			self::$cache = array_merge( self::defaults(), $stored );
		}

		return self::$cache;
	}

	/**
	 * Compute the default settings values.
	 *
	 * Domain default derives from the current site host, with any
	 * leading "www." stripped — most sites' From: header drops the
	 * www, so aligning the d= here without the prefix is the right
	 * default.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	private static function defaults(): array {
		return array(
			'domain'              => self::default_domain(),
			'selector'            => DEF_SELECTOR,
			'enabled'             => DEF_ENABLED,
			'remove_on_uninstall' => false,
		);
	}

	/**
	 * Compute the default signing domain from the current site URL.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	private static function default_domain(): string {
		$host   = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$result = preg_replace( '/^www\./i', '', $host );
		return is_string( $result ) ? $result : '';
	}

	/**
	 * Pull raw form values from $_POST after nonce verification.
	 *
	 * Centralised here so that the unsanitised superglobal access is
	 * confined to one spot, gated behind a phpcs:disable scope.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string>
	 */
	private static function collect_form_input(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Caller (handle_save) verifies the nonce before invoking this.
		$result = array(
			'domain'              => isset( $_POST['wpdkm_domain'] ) ? sanitize_text_field( wp_unslash( $_POST['wpdkm_domain'] ) ) : '',
			'selector'            => isset( $_POST['wpdkm_selector'] ) ? sanitize_text_field( wp_unslash( $_POST['wpdkm_selector'] ) ) : '',
			'enabled'             => isset( $_POST['wpdkm_enabled'] ) ? sanitize_text_field( wp_unslash( $_POST['wpdkm_enabled'] ) ) : '',
			'remove_on_uninstall' => isset( $_POST['wpdkm_remove_on_uninstall'] ) ? sanitize_text_field( wp_unslash( $_POST['wpdkm_remove_on_uninstall'] ) ) : '',
		);
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		return $result;
	}

	/**
	 * Validate and coerce form input into a settings sub-array.
	 *
	 * Returns null if any required field fails validation — caller
	 * surfaces a generic "invalid input" notice in response.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, string> $input Raw form values.
	 *
	 * @return array<string, mixed>|null
	 */
	private static function sanitize_settings( array $input ): ?array {
		$domain   = self::sanitize_domain( $input['domain'] ?? '' );
		$selector = self::sanitize_selector( $input['selector'] ?? '' );

		$result = null;

		if ( is_null( $domain ) || is_null( $selector ) ) {
			// At least one field failed validation — bail and let the caller surface a notice.
		} else {
			$result = array(
				'domain'              => $domain,
				'selector'            => $selector,
				'enabled'             => self::sanitize_enabled( $input['enabled'] ?? '' ),
				'remove_on_uninstall' => self::sanitize_enabled( $input['remove_on_uninstall'] ?? '' ),
			);
		}

		return $result;
	}

	/**
	 * Validate a domain string. Returns the canonicalised form or null.
	 *
	 * Lowercases the input and strips a leading "www." — DKIM `d=`
	 * conventionally matches the bare domain. Rejects anything that
	 * does not look like a hostname.
	 *
	 * @since 1.0.0
	 *
	 * @param string $raw Raw input.
	 *
	 * @return string|null
	 */
	private static function sanitize_domain( string $raw ): ?string {
		$candidate = strtolower( trim( $raw ) );
		$candidate = preg_replace( '/^www\./', '', $candidate );
		$valid     = filter_var( $candidate, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME );

		return false === $valid ? null : $candidate;
	}

	/**
	 * Validate a DNS-label selector. Returns lowercased form or null.
	 *
	 * RFC 1035: label must start and end with letter or digit, may
	 * contain letters / digits / hyphens, length 1–63.
	 *
	 * @since 1.0.0
	 *
	 * @param string $raw Raw input.
	 *
	 * @return string|null
	 */
	private static function sanitize_selector( string $raw ): ?string {
		$candidate = strtolower( trim( $raw ) );
		$matches   = (bool) preg_match( '/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $candidate );

		return $matches ? $candidate : null;
	}

	/**
	 * Coerce the checkbox value into a boolean.
	 *
	 * Treats any truthy form post (the checkbox being checked
	 * sends "1", "on", or similar) as true; absent fields are
	 * false. Always returns a bool — never fails validation.
	 *
	 * @since 1.0.0
	 *
	 * @param string $raw Raw input.
	 *
	 * @return bool
	 */
	private static function sanitize_enabled( string $raw ): bool {
		return (bool) filter_var( $raw, FILTER_VALIDATE_BOOLEAN );
	}

	/**
	 * Wrap the capability check used by the key-management handlers.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function require_manage_capability(): void {
		if ( current_user_can( CAP_MANAGE ) ) {
			// Authorised — continue.
		} else {
			wp_die(
				esc_html__( 'You do not have permission to perform this action.', 'wpmail-dkim-sign' ),
				'',
				array( 'response' => 403 )
			);
		}
	}

	/**
	 * Redirect back to the settings page with a notice query-arg.
	 *
	 * @since 1.0.0
	 *
	 * @param string $notice Notice identifier (see NOTICE_* constants).
	 *
	 * @return void
	 */
	private function redirect_with_notice( string $notice ): void {
		$target = get_settings_url( array( NOTICE_QUERY_PARAM => $notice ) );

		wp_safe_redirect( $target );
		exit;
	}
}
