<?php

namespace WicketAcc;

/**
 * @package  wicket-account-centre
 * @author  Wicket Inc.
 *
 * Plugin Name:       Wicket Account Centre
 * Plugin URI:        https://wicket.io
 * Description:       Customize WooCommerce my account features to build the Wicket Account Centre. Expands it with additional blocks and pages.
 * Version:           1.3.24
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
define('WICKET_ACC_TEMPLATES_FOLDER', 'account-centre');

/**
 * The main Wicket Account Centre class
 */
class WicketAcc
{
	protected $acc_index_slugs = [
		'en'    => 'my-account',
		'fr'    => 'mon-compte',
		'es'    => 'mi-cuenta',
	];

	protected array $acc_pages_map = [
		// Wicket pages
		'edit-profile'                   => 'Edit Profile',
		'events'                         => 'My Events',
		'jobs'                           => 'My Jobs',
		'job-post'                       => 'Post a Job',
		'change-password'                => 'Change Password',
		'organization-management'        => 'Organization Management',
		'acc_global-headerbanner'        => 'Global Header-Banner',
		// WooCommerce endpoints https://developer.woocommerce.com/docs/woocommerce-endpoints/
		//'order-pay'                      => 'Order Pay',
		//'order-received'                 => 'Order Received',
		'add-payment-method'             => 'Add Payment Method',
		'set-default-payment-method'     => 'Set Default Payment Method',
		'orders'                         => 'Orders',
		'view-order'                     => 'View Order',
		'downloads'                      => 'Downloads',
		'edit-account'                   => 'Edit Account',
		'edit-address'                   => 'Edit Address',
		'payment-methods'                => 'Payment Methods',
		//'customer-logout'                => 'Logout',
		// WooCommerce subscription endpoints
		'subscriptions'                  => 'Subscriptions',
	];

	/**
	 * Constructor
	 */
	public function __construct() {}

	/**
	 * Run
	 */
	public function run()
	{
		add_filter('wp_dropdown_pages', 'wicket_acc_alter_wp_job_manager_pages', 10, 3);

		register_activation_hook(__FILE__, [$this, 'plugin_activated']);

		$this->includes();

		if (is_admin()) {
			new AdminSettings();
		}

		new MdpApi();
		new Router();
		new Blocks();
		new Helpers();
		new Registers();
		new Profile();
		new Assets();

		// Conditionally load these classes
		if (!is_admin()) {
			new Language();
		}

		if (class_exists('WooCommerce')) {
			new WooCommerce();
		}
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
			'classes/class-wicket-acc-profile.php',
			'classes/class-wicket-acc-router.php',
			'classes/class-wicket-acc-helpers.php',
			'classes/class-wicket-acc-helpers-router.php',
			'classes/class-wicket-acc-assets.php',
		];

		$includes_global = [
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
	 * Plugin activation
	 */
	public function plugin_activated() {}
} // end Class.

// Initialize the plugin
$WicketAcc = new WicketAcc();
$WicketAcc->run();
