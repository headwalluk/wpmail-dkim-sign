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
	 * Constructor.
	 *
	 * Deliberately empty. Bootstrap work happens in run() so that it
	 * can be reasoned about as a single explicit step from the
	 * plugin entry file.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		// Intentionally empty. See run().
	}

	/**
	 * Register WordPress hooks for the plugin.
	 *
	 * Signer is always registered because outbound mail can be sent
	 * from any request context (front-end form submission, REST API,
	 * WP-Cron, admin action). Settings and Verifier are admin-only.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function run(): void {
		$this->get_signer()->register_hooks();
		$this->get_updater()->register_hooks();

		if ( is_admin() ) {
			$this->get_settings()->register_hooks();
			$this->get_verifier()->register_hooks();
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
			require_once WPDKM_PATH . 'includes/class-settings.php';
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
			require_once WPDKM_PATH . 'includes/class-keypair.php';
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
			require_once WPDKM_PATH . 'includes/class-signer.php';
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
			require_once WPDKM_PATH . 'includes/class-verifier.php';
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
			require_once WPDKM_PATH . 'includes/class-github-updater.php';
			$this->updater = new Github_Updater();
		}
		return $this->updater;
	}
}
