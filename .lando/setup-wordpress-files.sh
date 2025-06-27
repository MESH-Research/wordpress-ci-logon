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
ln -s /app /app/.lando/wordpress/wp-content/plugins/commons-connect-client
echo "Done setting up WordPress on $LANDO_SERVICE_NAME ..."

cd /app/