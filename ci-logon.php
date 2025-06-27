<?php
/**
 * Plugin Name: WordPress CI Logon
 * Description: A proof-of-concept WordPress plugin for integrating with CI Logon through OIDC
 * Requires at least: 6.7
 * Requires PHP: 8.4
 * Version: 1.0.0
 * Author: Mike Thicke / Mesh Research
 * Author URI: https://hcommons.org
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 * Text Domain: ci-logon
 *
 * @package MeshResearch\CILogon
 */

namespace MeshResearch\CILogon;

define ("CILOGON_BASE_DIR", __DIR__ . '/');
define ("CILOGON_BASE_URL", (defined('WPMU_PLUGIN_URL') ? WPMU_PLUGIN_URL : WP_PLUGIN_URL) . '/ci-logon/');
define ("CILOGON_REST_BASE", "ci-logon/v1");

if (file_exists(CILOGON_BASE_DIR . 'vendor/autoload.php')) {
    require_once CILOGON_BASE_DIR . 'vendor/autoload.php';
}

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Initialize the plugin
add_action('plugins_loaded', function() {
    \MeshResearch\CILogon\Plugin::get_instance();
});

// Add uninstall hook
register_uninstall_hook(__FILE__, '\MeshResearch\CILogon\cilogon_uninstall_callback');

function cilogon_uninstall_callback() {
    // Clean up user meta and options if needed
    delete_option('cilogon_settings');
}
