<?php

/**
 * @package  wicket-account-centre
 * @author  Wicket Inc.
 *
 * Plugin Name:       Wicket Account Centre
 * Plugin URI:        https://wicket.io
 * Description:       Customize WooCommerce my account features to build the Wicket Account Centre
 * Version:           1.1.0
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
define('WICKET_ACC_TEMPLATE_PATH', get_stylesheet_directory() . '/wicket-templates/');

if (!class_exists('Wicket_Acc')) {
	/**
	 * The main Wicket Account Centre class
	 */
	class Wicket_Acc
	{
		private static $instance;

		/**
		 * Constructor
		 */
		public function __construct()
		{
		}

		/**
		 * Get the singleton instance of the class
		 *
		 * @return object
		 */
		public static function instance()
		{
			if (!isset(self::$instance)) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Run
		 */
		public function run()
		{
			// Admin only
			$includes_admin = [
				//'classes/admin/class-wicket-acc-admin.php',
			];

			// Classes
			$include_classes = [
				'classes/class-wicket-acc-blocks.php',
				'classes/class-wicket-acc-front.php',
			];

			$includes_global = [
				'includes/ray-stub.php',
				'includes/helpers.php',
			];

			// Loop and include files
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

			add_action('wp_loaded', [$this, 'language']);
			add_action('init', [$this, 'register_custom_post_type']);
			add_action('after_setup_theme', [$this, 'custom_nav_menus']);
			add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
			add_action('before_woocommerce_init', [$this, 'HPOS_Compatibility']);
			add_filter('wp_dropdown_pages', 'wicket_acc_alter_wp_job_manager_pages', 10, 3);
			add_filter('post_type_link', 'wicket_acc_rewrite_permalinks', 50, 4);

			// Registration hook setting
			register_activation_hook(__FILE__, [$this, 'install_settings']);
		}

		/**
		 * HOPS compatibility for WooCommerce
		 *
		 * @return void
		 */
		public function HPOS_Compatibility()
		{
			if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
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

		/**
		 * Register custom post type
		 */
		public function register_custom_post_type()
		{
			// Set UI labels for custom post type
			$labels = [
				'name'               => esc_html__('Account Centre', 'wicket-acc'),
				'singular_name'      => esc_html__('Page', 'wicket-acc'),
				'add_new_item'       => esc_html__('Add New Page', 'wicket-acc'),
				'add_new'            => esc_html__('Add New Page', 'wicket-acc'),
				'edit_item'          => esc_html__('Edit Page', 'wicket-acc'),
				'view_item'          => esc_html__('View Page', 'wicket-acc'),
				'update_item'        => esc_html__('Update Page', 'wicket-acc'),
				'search_items'       => esc_html__('Search Page', 'wicket-acc'),
				'not_found'          => esc_html__('Not Found', 'wicket-acc'),
				'not_found_in_trash' => esc_html__('Not found in Trash', 'wicket-acc'),
				'menu_name'          => esc_html__('Account Centre', 'wicket-acc'),
				'parent_item_colon'  => '',
				'all_items'          => esc_html__('All Pages', 'wicket-acc'),
				'attributes'         => __('Pages Sorting Order'),
			];

			// Set other options for custom post type
			$args = [
				'labels'              => $labels,
				'menu_icon'           => '',
				'public'              => false,
				'publicly_queryable'  => true,
				'exclude_from_search' => true,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'query_var'           => true,
				'rewrite'             => true,
				'capability_type'     => 'post',
				'has_archive'         => false,
				'hierarchical'        => false,
				'menu_position'       => 30,
				'menu_icon'           => plugins_url('/assets/img/wicket-icon.png', __FILE__),
				'rewrite'             => [
					'slug'            => 'wicket_acc',
					'with_front'      => false,
				],
				'supports'            => [
					'title',
					'page-attributes',
					'editor'
				],
				'show_in_rest'        => true,
			];

			// Registering your Custom Post Type.
			register_post_type('wicket_acc', $args);
		}

		/**
		 * Register menu locations
		 */
		public function custom_nav_menus()
		{
			// This theme uses wp_nav_menu() in one location.
			register_nav_menus([
				'wicket-acc-nav' => esc_html__('Account Centre Menu', 'wicket-acc'),
			]);

			// This theme offers a secondary wp_nav_menu()
			register_nav_menus([
				'wicket-acc-nav-two' => esc_html__('Account Centre Secondary Menu', 'wicket-acc'),
			]);
		}

		/**
		 * Load plugin styles
		 */
		public function enqueue_assets()
		{
			wp_enqueue_style('wicket-widgets-icons', "https://fonts.googleapis.com/icon?family=Material+Icons");
			wp_enqueue_style('wicket-widgets-datepicker', plugins_url('/assets/css/react-datepicker.css', __FILE__));
		}
	} // end Class.

	// Initialize the plugin
	$Wicket_Acc = new Wicket_Acc();
	$Wicket_Acc->run();
}
