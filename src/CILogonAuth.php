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
class CILogonAuth
{
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
    public function __construct()
    {
        $this->config = [
            "provider_url" =>
                getenv("CILOGON_PROVIDER_URL") ?: "https://cilogon.org",
            "client_id" => getenv("CILOGON_CLIENT_ID"),
            "client_secret" => getenv("CILOGON_CLIENT_SECRET"),
            "redirect_uri" =>
                getenv("CILOGON_REDIRECT_URI") ?:
                "https://wordpress-ci-logon.lndo.site/wp-login.php",
            "callback_next" =>
                getenv("CILOGON_CALLBACK_NEXT") ?:
                "https://wordpress-ci-logon.lndo.site/wp-login.php",
            "scopes" => ["openid", "email", "profile"],
            "profiles_url" => getenv("PROFILES_URL") ?: "",
            "profiles_api_bearer_token" =>
                getenv("PROFILES_API_BEARER_TOKEN") ?: "",
        ];

        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks()
    {
        // Redirect login page to CI Logon
        add_action("login_init", [$this, "do_cilogon"]);
    }

    /**
     * Redirect WordPress login to CI Logon
     */
    public function do_cilogon()
    {
        // Don't redirect if this is a callback or specific WP login action
        if (isset($_GET["action"]) && $_GET["action"] === "cilogon_callback") {
            return;
        }

        // Don't redirect for logout, lostpassword, etc.
        if (
            isset($_GET["action"]) &&
            in_array($_GET["action"], [
                "logout",
                "lostpassword",
                "resetpass",
                "rp",
            ])
        ) {
            return;
        }

        // Don't redirect if user is already logged in and accessing wp-admin
        if (is_user_logged_in() && isset($_GET["redirect_to"])) {
            return;
        }

        try {
            error_log("CI Logon: Starting authentication redirect");
            $this->init_oidc_client();

            // Store redirect URL in session if provided
            if (isset($_GET["redirect_to"])) {
                if (!session_id()) {
                    session_start();
                }
                $_SESSION["cilogon_redirect_to"] = $_GET["redirect_to"];
                error_log(
                    "CI Logon: Stored redirect URL: " . $_GET["redirect_to"]
                );
            }

            $authenticated = $this->oidc_client->authenticate();
        } catch (Exception $e) {
            error_log("CI Logon authentication error: " . $e->getMessage());
            wp_die(
                "CI Logon authentication error: " . esc_html($e->getMessage())
            );
        }
        if ($authenticated) {
            $user_info = $this->get_user_info();
        }
        if ($user_info) {
            $user = $this->find_or_create_user($user_info);
        }
        if ($user) {
            wp_set_current_user($user->ID);
            wp_set_auth_cookie($user->ID);
            $redirect_to = admin_url();
            wp_safe_redirect($redirect_to);
            exit();
        }
    }

    /**
     * Initialize OpenID Connect client
     */
    private function init_oidc_client()
    {
        if (!$this->config["client_id"] || !$this->config["client_secret"]) {
            error_log("CI Logon: Missing client credentials");
            throw new Exception(
                "CI Logon client credentials not configured. Please set CILOGON_CLIENT_ID and CILOGON_CLIENT_SECRET environment variables."
            );
        }

        error_log(
            "CI Logon: Initializing OIDC client with provider: " .
                $this->config["provider_url"]
        );

        $this->oidc_client = new OpenIDConnectClient(
            $this->config["provider_url"],
            $this->config["client_id"],
            $this->config["client_secret"]
        );

        $this->oidc_client->setRedirectURL($this->config["redirect_uri"]);
        $this->oidc_client->addScope($this->config["scopes"]);

        $current_state = $this->oidc_client->getState();
        if (!$current_state) {
            error_log("CI Logon: No state found");
            $state = [
                "session_key" => bin2hex(random_bytes(16)),
                "callback_next" => $this->config["callback_next"],
            ];
            $encoded_state = base64_encode(json_encode($state));
            error_log("Setting state: " . var_export($encoded_state, true));
            $this->oidc_client->setState($encoded_state);
        }
    }

    /**
     * Get user info from Profiles subs endpoint
     */
    public function get_user_info()
    {
        $subs_endpoint = $this->config["profiles_url"] . "api/v1/subs/";
        $headers = [
            "Authorization" =>
                "Bearer " . $this->config["profiles_api_bearer_token"],
        ];
        error_log("Headers: " . var_export($headers, true));
        $sub = $this->oidc_client->getVerifiedClaims()->sub;
        error_log("Sub: " . var_export($sub, true));
        if (!$sub) {
            error_log("CI Logon: No sub found in verified claims");
            return false;
        }

        $response = wp_remote_get($subs_endpoint . "?sub=" . $sub, [
            "headers" => $headers,
        ]);

        if (is_wp_error($response)) {
            error_log(
                "CI Logon: Error fetching user info from Profiles API: " .
                    $response->get_error_message()
            );
            return false;
        }

        $user_info = json_decode(wp_remote_retrieve_body($response));
        error_log("Received user info: " . print_r($user_info, true));

        if (!$user_info) {
            error_log("CI Logon: Invalid response from Profiles API");
            return false;
        }

        if (
            !isset($user_info->data) ||
            !is_array($user_info->data) ||
            count($user_info->data) == 0
        ) {
            error_log(
                "CI Logon: No user data found in Profiles API response. Redirecting to link account page."
            );
            $this->link_account();
        }

        return $user_info->data[0];
    }

    public function link_account()
    {
        $id_token = $this->oidc_client->getIdToken();
        error_log(
            "ID Token payload:" .
                print_r($this->oidc_client->getIdTokenPayload(), true)
        );
        $key = hash("sha256", $this->config["profiles_api_bearer_token"], true);
        $iv = random_bytes(16);
        $cipherRaw = openssl_encrypt(
            $id_token,
            "AES-256-CBC",
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );
        $payload = base64_encode($iv . $cipherRaw);
        $url =
            $this->config["profiles_url"] .
            "associate?userinfo=" .
            rawurlencode($payload);
        error_log("CI Logon: Redirecting to link account page: " . $url);
        wp_redirect($url);
        exit();
    }

    /**
     * Find existing user or create new one based on CI Logon data
     */
    private function find_or_create_user($user_info)
    {
        $profile = $user_info->profile;
        $user = get_user_by("login", $profile->username);

        if ($user) {
            error_log(
                "CI Logon: Updating user meta for existing user: " . $user->ID
            );
            $this->update_user_meta($user, $profile);
            return $user;
        }

        // Create new user
        error_log("CI Logon: Creating new user for email: " . $profile->email);
        $username = $this->generate_username($profile);

        $user_data = [
            "user_login" => $username,
            "user_email" => $profile->email,
            "user_pass" => wp_generate_password(32, true, true), // Random password
            "first_name" => $profile->first_name ?? "",
            "last_name" => $profile->last_name ?? "",
            "display_name" => $profile->name ?? $profile->email,
            "role" => "administrator", // Default role
        ];

        $user_id = wp_insert_user($user_data);

        if (is_wp_error($user_id)) {
            error_log(
                "CI Logon: Failed to create user: " .
                    $user_id->get_error_message()
            );
            return $user_id;
        }

        error_log("CI Logon: Successfully created user with ID: " . $user_id);
        $user = get_user_by("id", $user_id);
        $this->update_user_meta($user, $profile);

        return $user;
    }

    /**
     * Generate unique username from user info
     */
    private function generate_username($profile)
    {
        $base_username = "";

        if (isset($profile->username)) {
            $base_username = sanitize_user($profile->username);
        } elseif (isset($profile->email)) {
            $base_username = sanitize_user(strstr($profile->email, "@", true));
        } else {
            $base_username = "cilogon_user";
        }

        $username = $base_username;
        $counter = 1;

        while (username_exists($username)) {
            $username = $base_username . "_" . $counter;
            $counter++;
        }

        return $username;
    }

    /**
     * Update user meta with CI Logon data
     */
    private function update_user_meta(WP_User $user, $user_info)
    {
        update_user_meta($user->ID, "cilogon_sub", $user_info->sub);
        update_user_meta($user->ID, "cilogon_iss", $user_info->iss);

        if (isset($user_info->eppn)) {
            update_user_meta($user->ID, "cilogon_eppn", $user_info->eppn);
        }

        if (isset($user_info->eptid)) {
            update_user_meta($user->ID, "cilogon_eptid", $user_info->eptid);
        }
    }

    /**
     * Get configuration for debugging
     */
    public function get_config()
    {
        return array_merge($this->config, [
            "client_secret" => $this->config["client_secret"]
                ? "[REDACTED]"
                : null,
        ]);
    }
}
