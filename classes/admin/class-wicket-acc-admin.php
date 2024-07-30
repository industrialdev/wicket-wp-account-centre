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
	}

	public function admin_register_options_page()
	{
		// Check function exists.
		if (function_exists('acf_add_options_sub_page')) {
			// Add sub page under custom post type.
			acf_add_options_sub_page(array(
				'page_title'  => 'ACC Options',
				'menu_title'  => 'ACC Options',
				'parent_slug' => 'edit.php?post_type=wicket_acc',
				'capability'  => 'manage_options',
				'menu_slug'   => 'wicket_acc_options',
				'redirect'    => false
			));
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
}
