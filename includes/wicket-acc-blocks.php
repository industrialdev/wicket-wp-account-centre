<?php

/**
 * ACF Blocks file for Wicket Account Centre Plugins
 *
 * @package  Wicket\Admin
 * @version  1.0.0
 */

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

if (!class_exists('Wicket_AC_Blocks')) {

	/**
	 * Wicket Blocks class
	 */
	class Wicket_AC_Blocks
	{

		private $current_group_being_saved;
		/**
		 * Constructor
		 */
		public function __construct()
		{

			// Add Wicket block catgories
			add_filter('block_categories_all', array($this, 'wicket_block_category'));

			// Add ACF blocks and field groups
			add_action('acf/init', array($this, 'wicket_load_blocks'), 5);
			add_filter('acf/settings/load_json', array($this, 'wicket_load_acf_field_group'));

			// SAVE AC plugin blocks in plugin directory
			add_action('acf/update_field_group', array($this, 'update_field_group'), 1, 1);
		}

		/**
		 * Add Wicket block categories
		 */
		public function wicket_block_category($categories)
		{
			$categories[] = array(
				'slug'  => 'wicket-account-center',
				'title' => 'Wicket_AC'
			);
			return $categories;
		}

		/**
		 * Load ACF Blocks
		 */
		public function wicket_load_blocks()
		{
			$blocks = $this->wicket_get_blocks();

			foreach ($blocks as $block) {
				if (file_exists(WICKET_ACC_PLUGIN_DIR . 'includes/blocks/' . $block . '/block.json')) {
					// Check if Block is already registered
					$registry = WP_Block_Type_Registry::get_instance();

					if (!$registry->get_registered('wicket-ac/' . $block)) {
						register_block_type(WICKET_ACC_PLUGIN_DIR . 'includes/blocks/' . $block . '/block.json');

						if (file_exists(WICKET_ACC_PLUGIN_DIR . 'includes/blocks/' . $block . '/style.css')) {
							wp_register_style('block-' . $block, WICKET_ACC_PLUGIN_DIR . 'includes/blocks/' . $block . '/style.css', array(), filemtime(WICKET_ACC_PLUGIN_DIR . 'includes/blocks/' . $block . '/style.css'));
						}

						// Main block file
						if (file_exists(WICKET_ACC_PLUGIN_DIR . 'includes/blocks/' . $block . '/init.php')) {
							include_once WICKET_ACC_PLUGIN_DIR . 'includes/blocks/' . $block . '/init.php';
						}

						// Blcok ajax file
						if (file_exists(WICKET_ACC_PLUGIN_DIR . 'includes/blocks/' . $block . '/ajax.php')) {
							include_once WICKET_ACC_PLUGIN_DIR . 'includes/blocks/' . $block . '/ajax.php';
						}
					}
				}
			}
		}

		/**
		 * Load ACF field groups for blocks
		 */
		public function wicket_load_acf_field_group($paths)
		{
			$paths[] = WICKET_ACC_PLUGIN_DIR . 'includes/acf-json';

			return $paths;
		}

		/**
		 * Get ACF Blocks from all folders included in the blocks folder
		 */
		public function wicket_get_blocks()
		{
			$blocks = scandir(WICKET_ACC_PLUGIN_DIR . 'includes/blocks/');
			$blocks = array_values(array_diff($blocks, array('..', '.', '.DS_Store', '_base-block')));
			return $blocks;
		}


		public function update_field_group($group)
		{
			// the purpose of this function is to see if we want to
			// change the location where this group is saved
			// and if we to to add a filter to alter the save path

			// first check to see if this is one of our groups
			if (!isset($group['location'][0][0]['value'])) {
				// not one or our groups
				return $group;
			}


			// store the group name and add action
			$this->current_group_being_saved = $group['location'][0][0]['value'];
			add_action('acf/settings/save_json',  array($this, 'wicket_acc_save_folder'), 999);

			// don't forget to return the groups
			return $group;
		}

		public function wicket_acc_save_folder($path)
		{

			// Save json file for blocks that have 'wicket-ac' in their name.
			if (str_starts_with($this->current_group_being_saved, 'wicket-ac')) {
				$path = WICKET_ACC_PLUGIN_DIR . 'includes/acf-json';
			}

			return $path;
		}
	} // end Class Wicket_Blocks.
	new Wicket_AC_Blocks();
}
