<?php

/**
 *
 * @package  wicket-account-centre
 * @author  Wicket Inc.
 *
 * Plugin Name:       Wicket Account Centre
 * Plugin URI:        https://wicket.io
 * Description:       Customize WooCommerce my account features to build the Wicket Account Centre
 * Version:           1.0.22
 * Author:            Wicket Inc.
 * Developed By:      Wicket Inc.
 * Author URI:        http://www.wicket.io
 * Support:           http://www.wicket.io
 * Domain Path:       /languages
 * Text Domain:       wicket-acc
 *
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

		// Deactivate the plugin.
		deactivate_plugins(__FILE__);

		$wicket_acc_plugin_check = '<div id="message" class="error">
						<p><strong>Wicket Account Centre plugin is inactive.</strong> The <a href="http://wordpress.org/extend/plugins/woocommerce/">WooCommerce plugin</a> must be active for this plugin to be used. Please install &amp; activate WooCommerce »</p></div>';

		echo wp_kses_post($wicket_acc_plugin_check);
	}

	add_action('admin_notices', 'wicket_acc_admin_notice');
}

if (!class_exists('Wicket_Acc_Main')) {
	/**
	 * The main Wicket Account Centre class
	 */
	class Wicket_Acc_Main
	{
		/**
		 * Constructor
		 */
		public function __construct()
		{

			// Define global constants
			$this->wicket_acc_global_constants_vars();

			// Load text domain
			add_action('wp_loaded', array($this, 'wicket_acc_init'));
			// Create custom post type
			add_action('init', array($this, 'wicket_acc_register_custom_post_type'));
			// Add menu locations
			add_action('after_setup_theme', array($this, 'wicket_acc_custom_nav_menus'));
			// Add Wicket Widgets Icons
			add_action('wp_enqueue_scripts', array($this, 'wicket_acc_styles'));

			// Registration hook setting
			register_activation_hook(__FILE__, array($this, 'wicket_acc_install_settings'));

			// Include other files
			if (is_admin()) {
				// include admin class
				include_once WICKET_ACC_PLUGIN_DIR . 'admin/class-wicket-acc-admin.php';
				include_once WICKET_ACC_PLUGIN_DIR . 'front/class-wicket-acc-front.php';
			} else {
				// include front class
				include_once WICKET_ACC_PLUGIN_DIR . 'front/class-wicket-acc-front.php';
			}

			// include ACF blocks
			include_once WICKET_ACC_PLUGIN_DIR . 'includes/wicket-acc-blocks.php';

			// include Wicket and AC plugin helper functions
			include_once WICKET_ACC_PLUGIN_DIR . 'includes/wicket-acc-helper-functions.php';

			// HOPS compatibility
			add_action('before_woocommerce_init', array($this, 'wicket_acc_HPOS_Compatibility'));

			add_filter('wp_dropdown_pages', 'wicket_acc_alter_wp_job_manager_pages', 10, 3);
			add_filter('post_type_link', 'wicket_acc_rewrite_permalinks', 50, 4);
		}

		public function wicket_acc_HPOS_Compatibility()
		{

			if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
			}
		}

		/**
		 * Define Global variables function
		 */
		public function wicket_acc_global_constants_vars()
		{
			if (!defined('WICKET_ACC_URL')) {
				define('WICKET_ACC_URL', plugin_dir_url(__FILE__));
			}

			if (!defined('WICKET_ACC_BASENAME')) {
				define('WICKET_ACC_BASENAME', plugin_basename(__FILE__));
			}

			if (!defined('WICKET_ACC_PLUGIN_DIR')) {
				define('WICKET_ACC_PLUGIN_DIR', plugin_dir_path(__FILE__));
			}

			if (!defined('WICKET_ACC_PLUGIN_URL')) {
				define('WICKET_ACC_PLUGIN_URL', plugin_dir_url(__FILE__));
			}

			if (!defined('WICKET_ACC_PLUGIN_VERSION')) {
				// Get version from plugin header
				$plugin_data = get_plugin_data(__FILE__);
				define('WICKET_ACC_PLUGIN_VERSION', $plugin_data['Version']);
			}
		}

		/**
		 * Plugin settings
		 */
		public function wicket_acc_install_settings()
		{
			// Default setting for plugin.
		}

		/**
		 * Load text domain
		 */
		public function wicket_acc_init()
		{
			if (function_exists('load_plugin_textdomain')) {
				load_plugin_textdomain('wicket-acc', false, dirname(plugin_basename(__FILE__)) . '/languages/');
			}
		}

		/**
		 * Register custom post type
		 */
		public function wicket_acc_register_custom_post_type()
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
		public function wicket_acc_custom_nav_menus()
		{
			// This theme uses wp_nav_menu() in one location.
			register_nav_menus(array(
				'wicket-acc-nav' => esc_html__('Account Centre Menu', 'wicket-acc'),
			));

			// This theme offers a secondary wp_nav_menu()
			register_nav_menus(array(
				'wicket-acc-nav-two' => esc_html__('Account Centre Secondary Menu', 'wicket-acc'),
			));
		}

		public function wicket_acc_styles()
		{
			wp_enqueue_style('wicket-widgets-icons', "https://fonts.googleapis.com/icon?family=Material+Icons");
			wp_enqueue_style('wicket-widgets-datepicker', plugins_url('/assets/css/react-datepicker.css', __FILE__));
		}
	} // end Class Wicket_Acc_Main_Class.

	new Wicket_Acc_Main();
}
