#!/bin/sh

if ! wp core is-installed --path=/app/.lando/wordpress ; then
	echo "Installing WordPress on $LANDO_SERVICE_NAME..."
	wp core install \
		--url=$LANDO_APP_NAME.$LANDO_DOMAIN \
		--title="WordPress CI Logon Test Site" \
		--admin_user=admin \
		--admin_password=admin \
		--admin_email=admin@$LANDO_APP_NAME.$LANDO_DOMAIN \
		--path=/app/.lando/wordpress
	echo "Done installing WordPress on $LANDO_SERVICE_NAME..."
else
	echo "WordPress is already installed on $LANDO_SERVICE_NAME..."
fi

#wp plugin activate commons-connect-client --path=/app/.lando/wordpress
wp plugin delete akismet --quiet --path=/app/.lando/wordpress
wp plugin delete hello --quiet --path=/app/.lando/wordpress
wp rewrite structure '/%postname%/' --hard --path=/app/.lando/wordpress
