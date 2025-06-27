#!/bin/sh
echo "Installing WP-CLI on $LANDO_SERVICE_NAME..."
cd /tmp
curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
chmod a+x wp-cli.phar
mv wp-cli.phar /usr/local/bin/wp
echo "Done installing WP-CLI on $LANDO_SERVICE_NAME..."