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
