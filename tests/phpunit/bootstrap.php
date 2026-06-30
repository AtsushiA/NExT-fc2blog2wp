<?php
/**
 * Bootstrap for WordPress integration tests.
 *
 * Loads the WordPress PHPUnit test suite (installed via bin/install-wp-tests.sh)
 * and manually loads the plugin's classes before WordPress finishes booting.
 *
 * @package NExT_FC2Blog2WP
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

$_phpunit_polyfills_path = getenv( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' );
if ( false === $_phpunit_polyfills_path ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', dirname( __DIR__, 2 ) . '/vendor/yoast/phpunit-polyfills' );
}

require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin's classes for the test run.
 *
 * The main plugin file only loads the classes under WP-CLI, so in the test
 * environment they are required directly.
 */
function _manually_load_plugin() {
	require dirname( __DIR__, 2 ) . '/class/fc2_html_parser.php';
	require dirname( __DIR__, 2 ) . '/class/next-fc2blog2wp_class.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

require $_tests_dir . '/includes/bootstrap.php';
