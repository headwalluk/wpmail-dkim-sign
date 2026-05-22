<?php
/**
 * Settings sub-service.
 *
 * Owns the admin settings page, the configuration options row, and
 * form-submission handlers (save, generate/regenerate/delete key).
 *
 * @package WPMail_DKIM_Sign
 */

namespace WPMail_DKIM_Sign;

defined( 'ABSPATH' ) || die();

/**
 * Settings
 *
 * Skeleton populated incrementally:
 * - M3: settings page UI, save handler, validators.
 * - M4: DNS-record display partial wired through settings page.
 * - M7: remove-on-uninstall checkbox.
 *
 * @since 0.1.0
 */
class Settings {

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 */
	public function __construct() {
		// Intentionally empty. See register_hooks().
	}

	/**
	 * Register WordPress hooks owned by this sub-service.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		// Placeholder — admin_menu / admin_init / admin_post_* hooks added in M3.
	}
}
