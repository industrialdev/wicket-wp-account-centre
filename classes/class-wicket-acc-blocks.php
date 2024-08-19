<?php

namespace WicketAcc;

use WP_Block_Type_Registry;

// No direct access
defined('ABSPATH') || exit;

/**
 * ACF Blocks file for Wicket Account Centre Plugins
 *
 * @package  Wicket\Admin
 * @version  1.0.0
 */

/**
 * Wicket Blocks class
 */
class Blocks extends WicketAcc
{

	private $current_group_being_saved;

	/**
	 * Constructor
	 */
	public function __construct()
	{
		add_filter('block_categories_all', [$this, 'editor_block_category']);

		add_action('acf/init', [$this, 'load_wicket_blocks'], 5);
		add_filter('acf/settings/load_json', [$this, 'load_acf_field_group']);

		add_action('acf/update_field_group', [$this, 'update_field_group'], 1, 1);
		add_action('acf/settings/save_json',  [$this, 'save_json_folder'], 100);
	}

	/**
	 * Add Wicket block categories for Gutenberg Editor
	 */
	public function editor_block_category($categories)
	{
		$categories[] = [
			'slug'  => 'wicket-account-center',
			'title' => 'Wicket_AC'
		];

		return $categories;
	}

	/**
	 * Load ACF Blocks
	 * Automatically register blocks from the blocks folder
	 * Also register block styles and scripts
	 *
	 * @return void
	 */
	public function load_wicket_blocks()
	{
		$blocks = $this->get_wicket_blocks();

		// No blocks found
		if (empty($blocks)) {
			return;
		}

		// Get Block Registry
		$registry = WP_Block_Type_Registry::get_instance();

		foreach ($blocks as $block) {
			if (file_exists(WICKET_ACC_PATH . 'includes/blocks/' . $block . '/block.json')) {
				// Check if $block already registered
				if ($registry->get_registered('wicket-ac/' . $block)) {
					continue;
				}

				register_block_type(WICKET_ACC_PATH . 'includes/blocks/' . $block . '/block.json');

				// When registering a block using block.json, the block style and script are automatically registered. We don't need to do it manually.

				// Main block file
				if (file_exists(WICKET_ACC_PATH . 'includes/blocks/' . $block . '/init.php')) {
					include_once WICKET_ACC_PATH . 'includes/blocks/' . $block . '/init.php';
				}

				// Block ajax file
				if (file_exists(WICKET_ACC_PATH . 'includes/blocks/' . $block . '/ajax.php')) {
					include_once WICKET_ACC_PATH . 'includes/blocks/' . $block . '/ajax.php';
				}
			} else {
				continue;
			}
		}
	}

	/**
	 * Load ACF field groups
	 */
	public function load_acf_field_group($paths)
	{
		$paths[] = WICKET_ACC_PATH . 'includes/acf-json';

		return $paths;
	}

	/**
	 * Get ACF Blocks from all folders included in the blocks folder
	 */
	public function get_wicket_blocks()
	{
		$blocks = scandir(WICKET_ACC_PATH . 'includes/blocks/');

		$blocks = array_values(array_diff($blocks, ['..', '.', '.DS_Store', '_ac-base-block']));

		// Also ignore any block folder that starts with an underscore
		$blocks = array_filter($blocks, function ($block) {
			return !str_starts_with($block, '_');
		});

		return $blocks;
	}

	/**
	 * ACF field group update
	 */
	public function update_field_group($group)
	{
		// Check if group name starts with 'ACC'
		if (!str_starts_with($group['title'], 'ACC')) {
			return $group;
		}

		// Store the group name
		$this->current_group_being_saved = $group['title'];

		return $group;
	}

	/**
	 * Save ACF field group in plugin directory
	 *
	 * @param string $path
	 *
	 * @return string
	 */
	public function save_json_folder($path)
	{
		// Save json file for blocks that have 'ACC' in their name.
		if (str_starts_with($this->current_group_being_saved, 'ACC')) {
			$path = WICKET_ACC_PATH . 'includes/acf-json';
		}

		return $path;
	}

	/**
	 * Get Block template
	 * Try to get the block template from child-theme/theme folder
	 * If not found, get the block template from plugin folder
	 *
	 * @param string $template_name
	 *
	 * @return bool|string Template path or false if not found
	 */
	public function get_block_template_path($template_name)
	{
		// Santize template name
		$template_name = sanitize_title($template_name);

		// Assume template is in child theme
		$template_path = WICKET_ACC_USER_TEMPLATE_PATH 	. $template_name . '.php';

		if (!file_exists($template_path)) {
			// If not found, check if template is in plugin folder
			$template_path = WICKET_ACC_PLUGIN_TEMPLATE_PATH . 'blocks/' . WICKET_ACC_SLUG . '/' . $template_name . '.php';
		}

		if (!file_exists($template_path)) {
			return false;
		}

		return $template_path;
	}

	/**
	 * Render Block template
	 *
	 * @param string $template_name
	 *
	 * @return void
	 */
	public function render_template($template_name = '', $args = [])
	{
		if (empty($template_name)) {
			return;
		}

		// Avoid false include
		if ($this->get_block_template_path($template_name) === false) {
			echo '<p>Template ' . $template_name . ' not found</p>';
			return;
		}

		$args = wp_parse_args($args, []);

		include $this->get_block_template_path($template_name);

		return;
	}
} // end Class Wicket_Blocks.
