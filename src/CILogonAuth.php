<?php
/**
 * CI Logon Authentication Handler
 *
 * @package MeshResearch\CILogon
 */

namespace MeshResearch\CILogon;

use Jumbojett\OpenIDConnectClient;
use WP_Error;
use WP_User;
use Exception;

/**
 * Handles CI Logon authentication via OpenID Connect
 */
class CILogonAuth {

    /**
     * OpenID Connect client instance
     *
     * @var OpenIDConnectClient
     */
    private $oidc_client;

    /**
     * CI Logon OIDC configuration
     *
     * @var array
     */
    private $config;

    /**
     * Constructor
     */
    public function __construct() {
        $this->config = [
            'provider_url' => getenv('CILOGON_PROVIDER_URL') ?: 'https://cilogon.org',
            'client_id' => getenv('CILOGON_CLIENT_ID'),
            'client_secret' => getenv('CILOGON_CLIENT_SECRET'),
            'redirect_uri' => home_url('/wp-admin/admin-ajax.php?action=cilogon_callback'),
            'scopes' => ['openid', 'email', 'profile']
        ];

        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Redirect login page to CI Logon
        add_action('login_init', [$this, 'redirect_to_cilogon']);

        // Handle CI Logon callback
        add_action('wp_ajax_nopriv_cilogon_callback', [$this, 'handle_callback']);
        add_action('wp_ajax_cilogon_callback', [$this, 'handle_callback']);

        // Add logout hook to handle CI Logon logout
        add_action('wp_logout', [$this, 'handle_logout']);

        // Add login redirect parameter
        add_filter('login_url', [$this, 'modify_login_url'], 10, 3);
    }

    /**
     * Redirect WordPress login to CI Logon
     */
    public function redirect_to_cilogon() {
        // Don't redirect if this is a callback or specific WP login action
        if (isset($_GET['action']) && $_GET['action'] === 'cilogon_callback') {
            return;
        }

        // Don't redirect for logout, lostpassword, etc.
        if (isset($_GET['action']) && in_array($_GET['action'], ['logout', 'lostpassword', 'resetpass', 'rp'])) {
            return;
        }

        // Don't redirect if user is already logged in and accessing wp-admin
        if (is_user_logged_in() && isset($_GET['redirect_to'])) {
            return;
        }

        try {
            error_log('CI Logon: Starting authentication redirect');
            $this->init_oidc_client();

            // Store redirect URL in session if provided
            if (isset($_GET['redirect_to'])) {
                if (!session_id()) {
                    session_start();
                }
                $_SESSION['cilogon_redirect_to'] = $_GET['redirect_to'];
                error_log('CI Logon: Stored redirect URL: ' . $_GET['redirect_to']);
            }

            $this->oidc_client->authenticate();
        } catch (Exception $e) {
            error_log('CI Logon authentication error: ' . $e->getMessage());
            wp_die('CI Logon authentication error: ' . esc_html($e->getMessage()));
        }
    }

    /**
     * Handle CI Logon callback
     */
    public function handle_callback() {
        try {
            error_log('CI Logon: Processing callback');
            $this->init_oidc_client();

            // Verify the authentication
            $this->oidc_client->authenticate();

            // Get user info from CI Logon
            $user_info = $this->oidc_client->requestUserInfo();

            if (!$user_info) {
                error_log('CI Logon: Failed to get user information from CI Logon');
                wp_die('Failed to get user information from CI Logon');
            }

            error_log('CI Logon: Received user info for email: ' . ($user_info->email ?? 'unknown'));

            // Find or create WordPress user
            $wp_user = $this->find_or_create_user($user_info);

            if (is_wp_error($wp_user)) {
                error_log('CI Logon: User creation failed: ' . $wp_user->get_error_message());
                wp_die('User creation failed: ' . esc_html($wp_user->get_error_message()));
            }

            // Log the user in
            wp_set_current_user($wp_user->ID);
            wp_set_auth_cookie($wp_user->ID, true);
            error_log('CI Logon: Successfully logged in user ID: ' . $wp_user->ID);

            // Redirect to intended destination
            if (!session_id()) {
                session_start();
            }
            $redirect_to = isset($_SESSION['cilogon_redirect_to']) ? $_SESSION['cilogon_redirect_to'] : admin_url();
            if (isset($_SESSION['cilogon_redirect_to'])) {
                unset($_SESSION['cilogon_redirect_to']);
            }

            error_log('CI Logon: Redirecting to: ' . $redirect_to);
            wp_redirect($redirect_to);
            exit;

        } catch (Exception $e) {
            error_log('CI Logon callback error: ' . $e->getMessage());
            wp_die('CI Logon callback error: ' . esc_html($e->getMessage()));
        }
    }

    /**
     * Handle logout
     */
    public function handle_logout() {
        error_log('CI Logon: User logged out');
        // Clear any CI Logon sessions
        if (session_id() && isset($_SESSION['cilogon_redirect_to'])) {
            unset($_SESSION['cilogon_redirect_to']);
        }
    }

    /**
     * Modify login URL to include CI Logon parameters
     */
    public function modify_login_url($login_url, $redirect, $force_reauth) {
        if ($redirect) {
            $login_url = add_query_arg('redirect_to', urlencode($redirect), $login_url);
        }
        return $login_url;
    }

    /**
     * Initialize OpenID Connect client
     */
    private function init_oidc_client() {
        if (!$this->config['client_id'] || !$this->config['client_secret']) {
            error_log('CI Logon: Missing client credentials');
            throw new Exception('CI Logon client credentials not configured. Please set CILOGON_CLIENT_ID and CILOGON_CLIENT_SECRET environment variables.');
        }

        error_log('CI Logon: Initializing OIDC client with provider: ' . $this->config['provider_url']);

        $this->oidc_client = new OpenIDConnectClient(
            $this->config['provider_url'],
            $this->config['client_id'],
            $this->config['client_secret']
        );

        $this->oidc_client->setRedirectURL($this->config['redirect_uri']);
        foreach ($this->config['scopes'] as $scope) {
            $this->oidc_client->addScope($scope);
        }
    }

    /**
     * Find existing user or create new one based on CI Logon data
     */
    private function find_or_create_user($user_info) {
        // Try to find user by email
        $user = get_user_by('email', $user_info->email);

        if ($user) {
            error_log('CI Logon: Found existing user with email: ' . $user_info->email);
            // Update user meta with CI Logon data
            $this->update_user_meta($user, $user_info);
            return $user;
        }

        // Create new user
        error_log('CI Logon: Creating new user for email: ' . $user_info->email);
        $username = $this->generate_username($user_info);

        $user_data = [
            'user_login' => $username,
            'user_email' => $user_info->email,
            'user_pass' => wp_generate_password(32, true, true), // Random password
            'first_name' => $user_info->given_name ?? '',
            'last_name' => $user_info->family_name ?? '',
            'display_name' => $user_info->name ?? $user_info->email,
            'role' => 'subscriber' // Default role
        ];

        $user_id = wp_insert_user($user_data);

        if (is_wp_error($user_id)) {
            error_log('CI Logon: Failed to create user: ' . $user_id->get_error_message());
            return $user_id;
        }

        error_log('CI Logon: Successfully created user with ID: ' . $user_id);
        $user = get_user_by('id', $user_id);
        $this->update_user_meta($user, $user_info);

        return $user;
    }

    /**
     * Generate unique username from user info
     */
    private function generate_username($user_info) {
        $base_username = '';

        if (isset($user_info->preferred_username)) {
            $base_username = sanitize_user($user_info->preferred_username);
        } elseif (isset($user_info->email)) {
            $base_username = sanitize_user(strstr($user_info->email, '@', true));
        } else {
            $base_username = 'cilogon_user';
        }

        $username = $base_username;
        $counter = 1;

        while (username_exists($username)) {
            $username = $base_username . '_' . $counter;
            $counter++;
        }

        return $username;
    }

    /**
     * Update user meta with CI Logon data
     */
    private function update_user_meta(WP_User $user, $user_info) {
        update_user_meta($user->ID, 'cilogon_sub', $user_info->sub);
        update_user_meta($user->ID, 'cilogon_iss', $user_info->iss);

        if (isset($user_info->eppn)) {
            update_user_meta($user->ID, 'cilogon_eppn', $user_info->eppn);
        }

        if (isset($user_info->eptid)) {
            update_user_meta($user->ID, 'cilogon_eptid', $user_info->eptid);
        }
    }

    /**
     * Get configuration for debugging
     */
    public function get_config() {
        return array_merge($this->config, [
            'client_secret' => $this->config['client_secret'] ? '[REDACTED]' : null
        ]);
    }
}
