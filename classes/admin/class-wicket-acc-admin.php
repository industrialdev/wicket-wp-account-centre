<?php

namespace WicketAcc;

// No direct access
defined('ABSPATH') || exit;

/**
 * Admin file for Wicket Account Centre
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
		add_action('acf/options_page/save', [$this, 'acc_options_save'], 10, 2);
		//add_filter('acf/load_field', [$this, 'acf_field_description_centre_spelling']);
	}

	/**
	 * Enqueue scripts and styles for admin.
	 */
	public function acc_admin_assets()
	{
		if (!is_admin()) {
			return;
		}

		wp_enqueue_script('wicket-acc-admin', plugins_url('../assets/js/wicket-acc-admin.js', __FILE__), ['jquery'], '1.0', true);
		wp_enqueue_style('wicket-acc-admin', plugins_url('../assets/css/wicket-acc-admin.css', __FILE__), false, '1.0');
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

	/**
	 * Actions when saving ACC Options (ACF based) in the backend.
	 */
	public function acc_options_save($post_id, $menu_slug)
	{
		if ($menu_slug !== 'wicket_acc_options') {
			return;
		}

		// Flush rewrite rules
		flush_rewrite_rules(false);
	}

	/**
	 * Modify the ACF field description for the Centre/Center spelling.
	 *
	 * @param array $field The ACF field array.
	 *
	 * @return array The modified ACF field array.
	 */
	public function acf_field_description_centre_spelling($field)
	{
		// Check if it's the correct field by comparing the field name and parent
		if ($field['name'] == 'ac_localization' && $field['parent'] == 'group_66a9987e2539f') {
			// Get current meta value for ac_localization from the options page
			$current_value = get_field('ac_localization', 'option');

			// Check if the current value is an array and format it for display
			if (is_array($current_value)) {
				$current_value = implode(', ', array_map('esc_html', $current_value));
			} else {
				$current_value = esc_html($current_value); // In case it's not an array
			}

			// Append the current value to the field's instructions
			$field['instructions'] .= sprintf(__('<br/>Current DB value: %s', 'wicket-acc'), $current_value);
		}

		return $field;
	}
}
