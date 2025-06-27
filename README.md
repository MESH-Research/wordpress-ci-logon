# WordPress CI Logon Plugin

A minimal WordPress plugin that integrates with CI Logon for authentication via OpenID Connect (OIDC). This plugin automatically redirects WordPress login attempts to CI Logon and handles the authentication callback.

## Features

- Automatic redirection from WordPress login to CI Logon
- OpenID Connect authentication flow
- Automatic user creation and login
- Environment variable configuration only
- Docker-friendly logging to standard output

## Requirements

- WordPress 6.7+
- PHP 8.4+
- CI Logon client credentials
- Lando development environment (recommended)

## Installation

1. Clone or download this plugin to your WordPress plugins directory
2. Run `composer install` to install dependencies
3. Configure environment variables (see Configuration section)
4. Activate the plugin in WordPress admin

## Configuration

### Environment Variables

Configure the environment variables in your `.lando.yml` file:

```yaml
services:
  appserver:
    overrides:
      environment:
        CILOGON_CLIENT_ID: your_client_id_here
        CILOGON_CLIENT_SECRET: your_client_secret_here
        CILOGON_PROVIDER_URL: https://cilogon.org
```

The provided `.lando.yml` already includes these variables - just replace the placeholder values with your actual CI Logon credentials.

### CI Logon Application Setup

1. Register your application at [CI Logon](https://cilogon.org)
2. Set the redirect URI to: `https://yoursite.com/wp-admin/admin-ajax.php?action=cilogon_callback`
3. Note your Client ID and Client Secret
4. Configure the environment variables above

## How It Works

1. When a user tries to access the WordPress login page, they are automatically redirected to CI Logon
2. After successful authentication at CI Logon, the user is redirected back to WordPress
3. The plugin creates a WordPress user account if one doesn't exist, or logs in the existing user
4. Users are redirected to their intended destination (or wp-admin by default)

## Logging

The plugin logs all authentication events using standard PHP logging functions:
- `error_log()` for general logging
- All logs go to Lando's container stdout/stderr
- View logs with `lando logs -s appserver`
- Enable WordPress debug logging for additional details

### Enable Debug Logging

Add to your `wp-config.php`:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## User Data Handling

The plugin stores the following CI Logon data as user meta:

- `cilogon_sub` - CI Logon subject identifier
- `cilogon_iss` - CI Logon issuer
- `cilogon_eppn` - eduPersonPrincipalName (if available)
- `cilogon_eptid` - eduPersonTargetedID (if available)

## Security Notes

- Client credentials are stored as environment variables (not in the database)
- Users are created with random passwords
- Authentication relies entirely on CI Logon verification
- Sessions are managed securely

## Troubleshooting

### Common Issues

1. **Redirect loop**: Check that your redirect URI is correctly configured in CI Logon
2. **Environment variables not loaded**: Verify your `.lando.yml` configuration and restart with `lando rebuild`
3. **Session errors**: Ensure PHP sessions are working properly

### Log Messages

Check your Lando logs with `lando logs -s appserver` for messages like:
- `CI Logon Plugin: Configuration loaded successfully`
- `CI Logon: Starting authentication redirect`
- `CI Logon: Successfully logged in user ID: X`

### Error Messages

The plugin will log helpful error messages for:
- Missing environment variables
- CI Logon authentication failures
- User creation failures

## Development

### File Structure

```
wordpress-ci-logon/
├── ci-logon.php              # Main plugin file
├── src/
│   ├── Plugin.php            # Main plugin class
│   └── CILogonAuth.php       # Authentication handler
├── composer.json             # Dependencies
└── README.md                 # This file
```

### Dependencies

- `jumbojett/openid-connect-php` - OpenID Connect client library
- `guzzlehttp/guzzle` - HTTP client

## License

MIT License - see LICENSE file for details.

## Support

This is a minimal authentication plugin designed for containerized environments. For production use, consider additional features like:

- User profile synchronization
- Role mapping
- Advanced error handling
- Multi-site support