#!/bin/sh

echo "Downloading WordPress on $LANDO_SERVICE_NAME ..."
cd /app/.lando
rm -rf wordpress/*
curl -O https://wordpress.org/latest.tar.gz
tar -xzf latest.tar.gz
rm latest.tar.gz
echo "Done downloading WordPress on $LANDO_SERVICE_NAME ..."

rm -rf wordpress/wp-content/plugins/commons-connect-client
rm -rf wordpress/wp-config.php
ln -s /app/.lando/wp-config.php /app/.lando/wordpress/wp-config.php
mkdir -p /app/.lando/wordpress/wp-content/mu-plugins/
ln -s /app /app/.lando/wordpress/wp-content/mu-plugins/ci-logon
# Create MU-plugin loader file
cat > /app/.lando/wordpress/wp-content/mu-plugins/ci-logon-loader.php << 'EOF'
<?php
/**
 * Plugin Name: WordPress CI Logon (MU)
 * Description: Must-use version of CI Logon plugin
 * Version: 1.0.0
 * Author: Mike Thicke / Mesh Research
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Load the main plugin file from the symlinked directory
if (file_exists(WPMU_PLUGIN_DIR . '/ci-logon/ci-logon.php')) {
    require_once WPMU_PLUGIN_DIR . '/ci-logon/ci-logon.php';
}
EOF
echo "Done setting up WordPress on $LANDO_SERVICE_NAME ..."

cd /app/
