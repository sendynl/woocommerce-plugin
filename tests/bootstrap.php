<?php

/**
 * PHPUnit bootstrap for the Sendy WooCommerce plugin.
 *
 * Loads the plugin's Composer autoloader (which also provides the Yoast
 * PHPUnit Polyfills the WordPress test suite requires), then boots the
 * WordPress integration test library installed by bin/install-wp-tests.sh.
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

$_tests_dir = getenv('WP_TESTS_DIR');

if (! $_tests_dir) {
    $_tests_dir = rtrim(sys_get_temp_dir(), '/\\') . '/wordpress-tests-lib';
}

if (! file_exists($_tests_dir . '/includes/functions.php')) {
    fwrite(
        STDERR,
        "Could not find the WordPress test suite in {$_tests_dir}.\n"
        . "Run bin/install-wp-tests.sh first (see CONTRIBUTING.md).\n"
    );
    exit(1);
}

require_once $_tests_dir . '/includes/functions.php';

require $_tests_dir . '/includes/bootstrap.php';
