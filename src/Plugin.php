<?php
/**
 * Main Plugin Class
 *
 * @package MeshResearch\CILogon
 */

namespace MeshResearch\CILogon;

/**
 * Main plugin initialization and management
 */
class Plugin {

    /**
     * Plugin instance
     *
     * @var Plugin
     */
    private static $instance = null;

    /**
     * CI Logon authentication handler
     *
     * @var CILogonAuth
     */
    private $auth_handler;

    /**
     * Get plugin instance (singleton)
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->init();
    }

    /**
     * Initialize the plugin
     */
    private function init() {
        // Start session if not already started
        if (!session_id()) {
            session_start();
        }

        // Check configuration and log warnings
        $this->check_configuration();

        // Initialize authentication handler
        $this->auth_handler = new CILogonAuth();

        // Hook into WordPress
        add_action('init', [$this, 'load_textdomain']);

        // Add activation and deactivation hooks
        register_activation_hook(CILOGON_BASE_DIR . 'ci-logon.php', [$this, 'activate']);
        register_deactivation_hook(CILOGON_BASE_DIR . 'ci-logon.php', [$this, 'deactivate']);
    }

    /**
     * Check configuration and log any issues
     */
    private function check_configuration() {
        $client_id = getenv('CILOGON_CLIENT_ID');
        $client_secret = getenv('CILOGON_CLIENT_SECRET');

        if (!$client_id || !$client_secret) {
            error_log('CI Logon Plugin: Missing required environment variables. Please set CILOGON_CLIENT_ID and CILOGON_CLIENT_SECRET.');
        } else {
            error_log('CI Logon Plugin: Configuration loaded successfully.');
        }
    }

    /**
     * Load plugin textdomain for translations
     */
    public function load_textdomain() {
        load_plugin_textdomain('ci-logon', false, dirname(plugin_basename(CILOGON_BASE_DIR . 'ci-logon.php')) . '/languages');
    }

    /**
     * Plugin activation
     */
    public function activate() {
        error_log('CI Logon Plugin: Activated');
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        error_log('CI Logon Plugin: Deactivated');
        flush_rewrite_rules();
    }

    /**
     * Get the authentication handler
     */
    public function get_auth_handler() {
        return $this->auth_handler;
    }

    /**
     * Check if plugin is properly configured
     */
    public function is_configured() {
        $client_id = getenv('CILOGON_CLIENT_ID');
        $client_secret = getenv('CILOGON_CLIENT_SECRET');

        return !empty($client_id) && !empty($client_secret);
    }
}
