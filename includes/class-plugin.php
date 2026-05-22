<?php
/**
 * Plugin orchestrator class.
 *
 * @package WPMail_DKIM_Sign
 */

namespace WPMail_DKIM_Sign;

defined( 'ABSPATH' ) || die();

/**
 * Plugin
 *
 * Top-level orchestrator. Owns hook registration and lazy-loads
 * the sub-services (Settings, Keypair, Signer, Verifier) on first
 * access via the get_*() accessors.
 *
 * Sub-services that need to reach each other do so via the global
 * wpmail_dkim_sign() accessor rather than constructor injection,
 * matching the WordPress lazy-loading pattern.
 *
 * @since 1.0.0
 */
class Plugin {

	/**
	 * Settings sub-service.
	 *
	 * @since 1.0.0
	 *
	 * @var Settings|null
	 */
	private ?Settings $settings = null;

	/**
	 * Keypair sub-service.
	 *
	 * @since 1.0.0
	 *
	 * @var Keypair|null
	 */
	private ?Keypair $keypair = null;

	/**
	 * Signer sub-service.
	 *
	 * @since 1.0.0
	 *
	 * @var Signer|null
	 */
	private ?Signer $signer = null;

	/**
	 * Verifier sub-service.
	 *
	 * @since 1.0.0
	 *
	 * @var Verifier|null
	 */
	private ?Verifier $verifier = null;

	/**
	 * GitHub auto-updater sub-service.
	 *
	 * @since 1.0.0
	 *
	 * @var Github_Updater|null
	 */
	private ?Github_Updater $updater = null;

	/**
	 * Register WordPress hooks for the plugin.
	 *
	 * All add_action / add_filter calls live here so the wiring is
	 * visible in one place when diagnosing hook-related issues.
	 *
	 * Signer + Github_Updater register in every request context;
	 * Settings + Verifier are admin-only.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function run(): void {
		// Mail signing — outbound mail can fire from any request context.
		add_action( 'phpmailer_init', array( $this->get_signer(), 'sign' ), SIGNER_PRIORITY, 1 );

		// GitHub auto-updater — WP refreshes its update_plugins transient
		// from both cron and admin contexts.
		add_filter( 'pre_set_site_transient_update_plugins', array( $this->get_updater(), 'check_for_update' ) );
		add_filter( 'plugins_api', array( $this->get_updater(), 'plugin_info' ), 10, 3 );
		add_action( 'upgrader_process_complete', array( $this->get_updater(), 'clear_cache' ), 10, 2 );

		if ( is_admin() ) {
			// Settings UI and form handlers.
			add_action( 'admin_menu', array( $this->get_settings(), 'register_menu' ) );
			add_action( 'admin_enqueue_scripts', array( $this->get_settings(), 'enqueue_admin_assets' ) );
			add_action( 'admin_post_' . ACTION_SAVE, array( $this->get_settings(), 'handle_save' ) );
			add_action( 'admin_post_' . ACTION_GENERATE_KEY, array( $this->get_settings(), 'handle_generate_key' ) );
			add_action( 'admin_post_' . ACTION_REGENERATE_KEY, array( $this->get_settings(), 'handle_regenerate_key' ) );
			add_action( 'admin_post_' . ACTION_DELETE_KEY, array( $this->get_settings(), 'handle_delete_key' ) );

			// Verify-tab diagnostic actions.
			add_action( 'admin_post_' . ACTION_TEST_EMAIL, array( $this->get_verifier(), 'handle_test_email' ) );
			add_action( 'admin_post_' . ACTION_CHECK_DNS, array( $this->get_verifier(), 'handle_check_dns' ) );
		} else {
			// Non-admin requests do not need the settings UI or diagnostic actions.
		}
	}

	/**
	 * Get the Settings sub-service, instantiating on first access.
	 *
	 * @since 1.0.0
	 *
	 * @return Settings
	 */
	public function get_settings(): Settings {
		if ( is_null( $this->settings ) ) {
			$this->settings = new Settings();
		}
		return $this->settings;
	}

	/**
	 * Get the Keypair sub-service, instantiating on first access.
	 *
	 * @since 1.0.0
	 *
	 * @return Keypair
	 */
	public function get_keypair(): Keypair {
		if ( is_null( $this->keypair ) ) {
			$this->keypair = new Keypair();
		}
		return $this->keypair;
	}

	/**
	 * Get the Signer sub-service, instantiating on first access.
	 *
	 * @since 1.0.0
	 *
	 * @return Signer
	 */
	public function get_signer(): Signer {
		if ( is_null( $this->signer ) ) {
			$this->signer = new Signer();
		}
		return $this->signer;
	}

	/**
	 * Get the Verifier sub-service, instantiating on first access.
	 *
	 * @since 1.0.0
	 *
	 * @return Verifier
	 */
	public function get_verifier(): Verifier {
		if ( is_null( $this->verifier ) ) {
			$this->verifier = new Verifier();
		}
		return $this->verifier;
	}

	/**
	 * Get the Github_Updater sub-service, instantiating on first access.
	 *
	 * @since 1.0.0
	 *
	 * @return Github_Updater
	 */
	public function get_updater(): Github_Updater {
		if ( is_null( $this->updater ) ) {
			$this->updater = new Github_Updater();
		}
		return $this->updater;
	}
}
