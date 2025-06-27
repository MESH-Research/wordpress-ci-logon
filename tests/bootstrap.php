<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package Commons_Connect_Client
 */

require 'vendor/autoload.php';

$_tests_dir = getenv( 'WP_TESTS_DIR' );
echo "WP_TESTS_DIR: {$_tests_dir}" . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

// Forward custom PHPUnit Polyfills configuration to PHPUnit bootstrap file.
$_phpunit_polyfills_path = getenv( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' );
if ( false !== $_phpunit_polyfills_path ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $_phpunit_polyfills_path );
}

if ( ! file_exists( "{$_tests_dir}/includes/functions.php" ) ) {
	echo "Could not find {$_tests_dir}/includes/functions.php, have you run bin/install-wp-tests.sh ?" . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit( 1 );
}

// Give access to tests_add_filter() function.
require_once "{$_tests_dir}/includes/functions.php";

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin() {
	define( 'CC_CLIENT_DOING_TESTING', true );
	include dirname( dirname( __FILE__ ) ) . '/.lando/wordpress/wp-content/plugins/bbpress/bbpress.php';
	require dirname( dirname( __FILE__ ) ) . '/cc-client.php';

	// Reset the search service
	// We do it here to avoid side effects that can result from running it in
	// setUp() or tearDown() methods. This does mean that test pollution is
	// possible as the search index is not reset between tests.
	$options = new MeshResearch\CCClient\CCClientOptions(
		cc_search_key: '12345',
		cc_search_endpoint: 'http://commonsconnect-search.lndo.site/v1',
		cc_search_admin_key: '12345',
		incremental_provisioning_enabled: false
	);
	$search_api = new MeshResearch\CCClient\Search\SearchAPI( $options );
	$search_api->reset_index();
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Start up the WP testing environment.
require "{$_tests_dir}/includes/bootstrap.php";
