<?php

namespace WicketAcc;

/**
 * @package  wicket-account-centre
 * @author  Wicket Inc.
 *
 * Plugin Name:       Wicket Account Centre
 * Plugin URI:        https://wicket.io
 * Description:       Customize WooCommerce my account features to build the Wicket Account Centre. Expands it with additional blocks and pages.
 * Version:           1.1.14
 * Author:            Wicket Inc.
 * Developed By:      Wicket Inc.
 * Author URI:        https://wicket.io
 * Support:           https://wicket.io
 * Domain Path:       /languages
 * Text Domain:       wicket-acc
 */

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')), true)) {
	/**
	 * Show Required Plugin Notice
	 */
	function wicket_acc_admin_notice()
	{
		// Deactivate this plugin.
		deactivate_plugins(__FILE__);

		$wicket_acc_plugin_check = '<div id="message" class="error">
						<p><strong>Wicket Account Centre plugin is inactive.</strong> The <a href="https://wordpress.org/extend/plugins/woocommerce/">WooCommerce plugin</a> must be active for this plugin to be used. Please install &amp; activate WooCommerce »</p></div>';

		echo wp_kses_post($wicket_acc_plugin_check);
	}

	add_action('admin_notices', 'wicket_acc_admin_notice');
}

// Constants
define('WICKET_ACC_VERSION', get_file_data(__FILE__, ['Version' => 'Version'], false)['Version']);
define('WICKET_ACC_PATH', plugin_dir_path(__FILE__));
define('WICKET_ACC_URL', plugin_dir_url(__FILE__));
define('WICKET_ACC_BASENAME', plugin_basename(__FILE__));
define('WICKET_ACC_UPLOADS_PATH', wp_get_upload_dir()['basedir'] . '/wicket-account-center/');
define('WICKET_ACC_UPLOADS_URL', wp_get_upload_dir()['baseurl'] . '/wicket-account-center/');
define('WICKET_ACC_PLUGIN_TEMPLATE_PATH', WICKET_ACC_PATH . 'templates-wicket/');
define('WICKET_ACC_USER_TEMPLATE_PATH', get_stylesheet_directory() . '/templates-wicket/');
define('WICKET_ACC_SLUG', 'account-centre'); // We take care of multi-language support later on the plugin

/**
 * The main Wicket Account Centre class
 */
class WicketAcc
{
	/**
	 * Constructor
	 */
	public function __construct() {}

	/**
	 * Run
	 */
	public function run()
	{
		add_action('wp_loaded', [$this, 'language']);
		add_filter('wp_dropdown_pages', 'wicket_acc_alter_wp_job_manager_pages', 10, 3);
		add_filter('post_type_link', 'wicket_acc_rewrite_permalinks', 50, 4);

		// Registration hook setting
		register_activation_hook(__FILE__, [$this, 'install_settings']);

		// Includes
		$this->includes();

		// Init classes
		if (is_admin()) {
			new AdminSettings();
		}

		new MdpApi();
		new Language();
		new Router();
		new WooCommerce();
		new Blocks();
		new Front();
		new Profile();
		new Helpers();
		new Registers();
	}

	/**
	 * Plugin includes
	 *
	 * @return void
	 */
	protected function includes()
	{
		// Includes
		$includes_admin = [
			'classes/admin/class-wicket-acc-admin.php',
		];

		$include_classes = [
			'classes/class-wicket-acc-language.php',
			'classes/class-wicket-acc-mdp-api.php',
			'classes/class-wicket-acc-woocommerce.php',
			'classes/class-wicket-acc-registers.php',
			'classes/class-wicket-acc-blocks.php',
			'classes/class-wicket-acc-front.php',
			'classes/class-wicket-acc-profile.php',
			'classes/class-wicket-acc-router.php',
			'classes/class-wicket-acc-helpers.php',
			'classes/class-wicket-acc-helpers-router.php',
		];

		$includes_global = [
			'includes/admin/options-main.php',
			'includes/ray-stub.php',
			'includes/helpers.php',
			'includes/deprecated.php',
		];

		// Admin Classes
		if (is_admin()) {
			// Admin only
			if (is_array($includes_admin) && !empty($includes_admin)) {
				foreach ($includes_admin as $file) {
					if (file_exists(WICKET_ACC_PATH . $file)) {
						include_once WICKET_ACC_PATH . $file;
					}
				}
			}
		}

		// Classes
		if (is_array($include_classes) && !empty($include_classes)) {
			foreach ($include_classes as $file) {
				if (file_exists(WICKET_ACC_PATH . $file)) {
					include_once WICKET_ACC_PATH . $file;
				}
			}
		}

		// Global
		if (is_array($includes_global) && !empty($includes_global)) {
			foreach ($includes_global as $file) {
				if (file_exists(WICKET_ACC_PATH . $file)) {
					include_once WICKET_ACC_PATH . $file;
				}
			}
		}
	}

	/**
	 * Plugin settings
	 */
	public function install_settings()
	{
		// Default setting for plugin.
	}

	/**
	 * Load text domain
	 */
	public function language()
	{
		if (function_exists('load_plugin_textdomain')) {
			load_plugin_textdomain('wicket-acc', false, dirname(plugin_basename(__FILE__)) . '/languages/');
		}
	}
} // end Class.

// Initialize the plugin
$WicketAcc = new WicketAcc();
$WicketAcc->run();
