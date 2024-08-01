<?php

namespace WicketAcc;

// No direct access
defined('ABSPATH') || exit;

/**
 * Admin file for Wicket Account Centre
 *
 * @package  wicket-account-centre
 * @version  1.0.0
 */

/**
 * Admin class of module
 */
class AdminSettings extends WicketAcc
{
	/**
	 * Constructor of class
	 */
	public function __construct()
	{
		add_action('acf/init', [$this, 'admin_register_options_page']);
		add_action('admin_notices', [$this, 'acf_json_folder_permissions']);
		add_action('admin_enqueue_scripts', [$this, 'acc_admin_assets']);
	}

	/**
	 * Enqueue scripts and styles for admin.
	 */
	public function acc_admin_assets()
	{
		if (!is_admin()) {
			return;
		}

		wp_enqueue_script('wicket_acc_admin', plugins_url('../assets/js/wicket_acc_admin.js', __FILE__), ['jquery'], '1.0', true);
		wp_enqueue_style('wicket_acc_admin', plugins_url('../assets/css/wicket_acc_admin.css', __FILE__), false, '1.0');
	}

	public function admin_register_options_page()
	{
		// Check function exists.
		if (function_exists('acf_add_options_sub_page')) {
			// Add sub page under custom post type
			acf_add_options_sub_page([
				'page_title'  => 'ACC Options',
				'menu_title'  => 'ACC Options',
				'parent_slug' => 'edit.php?post_type=wicket_acc',
				'capability'  => 'manage_options',
				'menu_slug'   => 'wicket_acc_options',
				'redirect'    => false
			]);
		}
	}

	/**
	 * Add menu in admin bar
	 *
	 * @return void
	 */
	public function acc_admin_menu()
	{
		add_submenu_page(
			'edit.php?post_type=wicket_acc', // parent_slug
			esc_html__('Account Centre Page Editor', 'wicket-acc'), // page title
			esc_html__('Settings', 'wicket-acc'), // menu title
			'manage_options', // capability
			'customize-my-account-page-layout', // slug
			[$this, 'wicket_acc_settings_callback'], // callback function
			10 // position
		);
	}

	/**
	 * On ACF pages at the backend,
	 * warn the user if /includes/acf-json/ folder is not writable.
	 */
	public function acf_json_folder_permissions()
	{
		if (!is_admin()) {
			return;
		}

		$acf_json_folder = WICKET_ACC_PATH . 'includes/acf-json/';

		if (!is_writable($acf_json_folder)) {
			echo '<div class="notice notice-error"><p><strong>ACF JSON folder not writable</strong></p><p>The ' . $acf_json_folder . ' folder is not writable. Please make sure the folder is writable by the server.</p><p>Not solving this issue, will result in ACF fields not being saved at plugin level and will not be visible on other sites backend.</p></div>';
		}
	}
}
