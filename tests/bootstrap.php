<?php
/**
 * PHPUnit Bootstrap File.
 *
 * Loads Composer autoloader and defines essential WordPress constants for isolated unit testing.
 */

declare(strict_types=1);

// Define essential WordPress constants
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/');
}

if (!defined('DAY_IN_SECONDS')) {
    define('DAY_IN_SECONDS', 86400);
}

if (!defined('HOUR_IN_SECONDS')) {
    define('HOUR_IN_SECONDS', 3600);
}

if (!defined('MINUTE_IN_SECONDS')) {
    define('MINUTE_IN_SECONDS', 60);
}

if (!defined('WEEK_IN_SECONDS')) {
    define('WEEK_IN_SECONDS', 604800);
}

if (!defined('MONTH_IN_SECONDS')) {
    define('MONTH_IN_SECONDS', 2592000);
}

if (!defined('YEAR_IN_SECONDS')) {
    define('YEAR_IN_SECONDS', 31536000);
}

// Mock WordPress classes
if (!class_exists('WP_Widget')) {
    class WP_Widget
    {
        public function __construct($id_base = '', $name = '', $widget_options = [], $control_options = []) {}
    }
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

// Mock WordPress functions BEFORE loading the main class
\Brain\Monkey\setUp();
\Brain\Monkey\Functions\stubs([
    'get_file_data' => ['Version' => '1.6.0'],
    'plugin_dir_path' => __DIR__ . '/../../',
    'plugin_dir_url' => 'http://example.com/wp-content/plugins/wicket-wp-account-centre/',
    'plugin_basename' => 'wicket-wp-account-centre/wicket-wp-account-centre.php',
    'wp_get_upload_dir' => [
        'basedir' => '/tmp/uploads',
        'baseurl' => 'http://example.com/uploads',
    ],
    'get_stylesheet_directory' => '/tmp/theme',
    'get_stylesheet_directory_uri' => 'http://example.com/theme',
    'add_action' => null,
    'add_filter' => null,
]);

// Load the main WicketAcc class for tests
require_once dirname(__DIR__) . '/class-wicket-acc-main.php';

\Brain\Monkey\tearDown();

