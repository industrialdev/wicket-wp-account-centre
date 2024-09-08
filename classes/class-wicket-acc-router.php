<?php

namespace WicketAcc;

// No direct access
defined('ABSPATH') || exit;

/**
 * Router Class
 * Get ACC pages, IDs, slugs, and data needed to jump between them.
 *
 * Migration to 1.3.x or greater, from 1.2.x or lower.
 * Manual steps:
 *
 * 1. Make sure ACF json files are updated and synced. Mind "wicket_acc" definitions inside them. They nshould be "my-account" now.
 *
 * 2. Set my-account as Translatable
 * admin.php?page=tm/menu/settings
 *
 * 3. WPML Troubleshooting, do:
 * admin.php?page=sitepress-multilingual-cms%2Fmenu%2Ftroubleshooting.php
 * 		Set Language Information
 * 		Fix terms count
 * 		Fix post type assignments for translation
 * Then flush rewrite rules (save WP Permalinks)
 *
 * 4. Re-link FR pages to their EN counterparts. One by one.
 * Edit a FR page. Sidebar, change Language to FR, confirm and save.
 * Now click the link "Connect with translations".
 * Find the correct EN page, select it and click "Ok".
 * On "Connect this post?" UNCHECK "Make FR the original language..." and click Assign.
 * Done.
 *
 * 5. If you need to translate my-account CPT slug, use WPML directly:
 * https://wpml.org/documentation/getting-started-guide/translating-page-slugs/
 */
class Router extends WicketAcc
{
	private $acc_page_id_cache = null;
	private $acc_slug_cache    = null;
	private $acc_url_cache     = null;

	/**
	 * Constructor
	 */
	public function __construct()
	{
		// DEBUG ONLY, check environment
		if (defined('WP_ENV') && WP_ENV === 'development') {
			flush_rewrite_rules();
		}

		add_action('admin_init', [$this, 'init_all_pages']);
		add_action('admin_init', [$this, 'maybe_migrate_to_my_account'], 1750);
		add_action('init', [$this, 'acc_pages_template']);
		add_filter('archive_template', [$this, 'custom_archive_template'], 1750);
		add_action('template_redirect', [$this, 'redirect_woocommerce_myaccount']);
	}

	/**
	 * Get ACC page ID
	 *
	 * @return int
	 */
	public function get_acc_page_id()
	{
		if ($this->acc_page_id_cache === null) {
			$this->acc_page_id_cache = get_field('acc_page_dashboard', 'option');
		}
		return $this->acc_page_id_cache;
	}

	/**
	 * Get ACC page Slug
	 *
	 * @return string
	 */
	public function get_acc_slug()
	{
		if ($this->acc_slug_cache === null) {
			$acc_page_id = $this->get_acc_page_id();
			$this->acc_slug_cache = get_post($acc_page_id)->post_name;
		}
		return $this->acc_slug_cache;
	}

	/**
	 * Get ACC Page URL
	 *
	 * @return string
	 */
	public function get_acc_url()
	{
		if ($this->acc_url_cache === null) {
			$acc_page_id = $this->get_acc_page_id();
			$this->acc_url_cache = get_permalink($acc_page_id);
		}
		return $this->acc_url_cache;
	}

	/**
	 * Create page for ACC
	 * Index, Edit Profile, Events, Event Single, etc.
	 *
	 * @param string $slug
	 * @param string $name
	 *
	 * @return mixed	ID of created page or false
	 */
	public function create_page($slug, $name)
	{
		// Let's ensure our setting option doesn't have a page defined yet
		$page_id = get_field('acc_page_' . $slug, 'option');

		if ($page_id) {
			return $page_id;
		}

		$page_id = $this->get_page_id_by_slug($slug);

		if ($page_id) {
			update_field('acc_page_' . $slug, $page_id, 'option');

			return $page_id;
		}

		// Create requested page as a child of ACC index page
		$page_id = wp_insert_post(
			[
				'post_type'      => 'my-account',
				'post_author'    => wp_get_current_user()->ID,
				'post_title'     => $name,
				'post_name'      => $slug,
				'post_status'    => 'publish',
				'comment_status' => 'closed',
				'post_content'   => '',
			]
		);

		if ($page_id) {
			// Save ACF option field
			update_field('acc_page_' . $slug, $page_id, 'option');

			return $page_id;
		}

		return false;
	}

	/**
	 * Get ACC page ID by slug
	 *
	 * @param string $slug
	 *
	 * @return int|bool Page ID or false if not found
	 */
	public function get_page_id_by_slug($slug)
	{
		global $wpdb;

		$page_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM $wpdb->posts WHERE post_name = %s AND post_type = 'my-account' AND post_status = 'publish'",
				$slug
			)
		);

		// If page not found or is not a number, return false
		if (empty($page_id) || !is_numeric($page_id)) {
			return false;
		}

		return $page_id;
	}

	/**
	 * Create all ACC pages
	 * Run only once
	 *
	 * @return void
	 */
	public function init_all_pages()
	{
		// Create all other pages
		foreach ($this->acc_pages_map as $slug => $name) {
			$this->create_page($slug, $name);
		}

		$this->maybe_create_acc_dashboard_page();
	}

	/**
	 * Maybe create ACC page
	 *
	 * return void
	 */
	public function maybe_create_acc_dashboard_page()
	{
		// Check if we've already created the main page
		if (get_option('wicket_acc_created_dashboard_page')) {
			return;
		}

		$set_slug = WACC()->get_slug();
		$set_name = WACC()->get_name();

		// Empty?
		if (empty($set_slug) || empty($set_name)) {
			return;
		}

		// Create page
		$this->create_page($set_slug, $set_name);

		// Save option to track that we've created the page
		update_option('wicket_acc_created_dashboard_page', true);
	}

	/**
	 * Detects if there's at least one page on the DB with CPT wicket_acc (pre 2024-09-07)
	 * If there're one or more, change the CPT to my-account
	 *
	 * @return void
	 */
	public function maybe_migrate_to_my_account()
	{
		// Check if we've already changed the CPT to my-account
		if (get_option('wicket_acc_cpt_changed_to_my_account')) {
			return;
		}

		// Get all posts with old CPT slug
		$args = [
			'post_type'      => 'wicket_acc',
			'post_status'    => 'any',
			'posts_per_page' => -1,
		];

		$posts = get_posts($args);

		if ($posts) {
			foreach ($posts as $post) {
				$this->update_post_type($post->ID);
			}
		}

		// Get current WooCommerce "myaccount" page ID
		$woo_my_account_page_id = wc_get_page_id('myaccount');

		if ($woo_my_account_page_id > 0) {
			// Change page slug to wc-account
			wp_update_post(
				[
					'ID' => $woo_my_account_page_id,
					'post_name' => 'wc-account',
				]
			);
		}

		// Migrate ACF field value from acc_page_account-centre to acc_page_dashboard
		$acc_page_account_centre_id = get_field('acc_page_account-centre', 'option');

		if ($acc_page_account_centre_id) {
			update_field('acc_page_dashboard', $acc_page_account_centre_id, 'option');
		}

		// Delete old ACF field
		delete_field('acc_page_account-centre', 'option');

		// Rename Global Banner page slug to acc_global-headerbanner
		$acc_old_headerbanner = get_field('acc_global-banner', 'option');

		if ($acc_old_headerbanner) {
			// Rename post slug
			wp_update_post(
				[
					'ID' => $acc_old_headerbanner,
					'post_name' => 'acc_global-headerbanner',
				]
			);

			// Update new ACF field
			update_field('acc_global-headerbanner', $acc_old_headerbanner, 'option');

			// Delete ACF field
			delete_field('acc_global-banner', 'option');
		}

		// Update WP nav menus
		$this->update_nav_menus();

		// Flush rewrite rules, because we've changed the CPT slug
		flush_rewrite_rules();

		// Empty caches
		wp_cache_flush();

		// Save an option to track that we've changed the CPT to my-account
		update_option('wicket_acc_cpt_changed_to_my_account', true);
	}

	/**
	 * Update WP nav menus
	 *
	 * @return void
	 */
	private function update_nav_menus()
	{
		$menus = wp_get_nav_menus();
		$languages = apply_filters('wpml_active_languages', null, 'skip_missing=0&orderby=code');

		foreach ($menus as $menu) {
			$menu_items = wp_get_nav_menu_items($menu->term_id);

			foreach ($menu_items as $item) {
				$trid = apply_filters('wpml_element_trid', null, $item->ID, 'post_nav_menu_item');
				$translations = apply_filters('wpml_get_element_translations', null, $trid, 'post_nav_menu_item');

				foreach ($languages as $lang_code => $language_info) {
					$translated_item_id = isset($translations[$lang_code]) ? $translations[$lang_code]->element_id : null;

					if ($translated_item_id) {
						$translated_item = get_post($translated_item_id);
						$updated = false;

						// Update URL
						$old_url_parts = [
							'EN' => '/account-centre',
							'FR' => '/mon-compte',
							'ES' => '/mi-cuenta'
						];

						foreach ($old_url_parts as $url_part) {
							if (strpos($translated_item->url, $url_part) !== false) {
								$translated_item->url = str_replace($url_part, '/my-account', $translated_item->url);
								$updated = true;
							}
						}

						// Always update object and type
						$translated_item->object = 'my-account';
						$translated_item->type = 'post_type';
						$updated = true;

						// Update object_id
						$post_id = url_to_postid($translated_item->url);
						if ($post_id) {
							$translated_item->object_id = $post_id;
						} else {
							// If we can't find the post ID, create a new page
							$new_page_id = $this->create_page(basename($translated_item->url), $translated_item->post_title);
							if ($new_page_id) {
								$translated_item->object_id = $new_page_id;
							}
						}

						if ($updated) {
							$menu_item_data = [
								'menu-item-title'     => $translated_item->post_title,
								'menu-item-object'    => $translated_item->object,
								'menu-item-object-id' => $translated_item->object_id,
								'menu-item-type'      => $translated_item->type,
								'menu-item-url'       => $translated_item->url,
								'menu-item-status'    => 'publish',
								'menu-item-parent-id' => $translated_item->menu_item_parent,
							];

							// Update the menu item
							wp_update_nav_menu_item($menu->term_id, $translated_item_id, $menu_item_data);
						}
					}
				}
			}

			// Refresh menu cache
			wp_cache_delete("wp_get_nav_menu_items_{$menu->term_id}", 'nav_menu_items');
		}
	}

	/**
	 * Set post type to my-account
	 *
	 * @param int $post_id
	 *
	 * @return void
	 */
	private function update_post_type($post_id)
	{
		wp_update_post(
			[
				'ID' => $post_id,
				'post_type' => 'my-account',
			]
		);
	}

	/**
	 * Adjust language switcher URLs
	 *
	 * @param array $languages
	 *
	 * @return array
	 */
	public function adjust_language_switcher_urls($languages)
	{
		foreach ($languages as $lang_code => &$language) {
			$translated_page_id = apply_filters('wpml_object_id', $this->get_acc_page_id(), 'page', true, $lang_code);
			$language['url'] = get_permalink($translated_page_id);
		}
		return $languages;
	}

	/**
	 * Get Wicket ACC template
	 *
	 * @return string|false
	 */
	private function get_wicket_acc_template()
	{
		$user_template = WICKET_ACC_USER_TEMPLATE_PATH . 'account-centre/page-wicket_acc.php';
		$plugin_template = WICKET_ACC_PLUGIN_TEMPLATE_PATH . 'account-centre/page-wicket_acc.php';

		if (file_exists($user_template)) {
			return $user_template;
		} elseif (file_exists($plugin_template)) {
			return $plugin_template;
		}

		return false;
	}

	/**
	 * Make all ACC CPT my-account pages, always load the template from get_wicket_acc_template() method
	 *
	 * @return void
	 */
	public function acc_pages_template()
	{
		add_filter('single_template', function ($single_template) {
			global $post;

			if ($post->post_type == 'my-account') {
				$template = $this->get_wicket_acc_template();
				if ($template) {
					return $template;
				}
			}

			return $single_template;
		});
	}

	/**
	 * Custom archive template for my-account CPT
	 *
	 * @param string $template
	 *
	 * @return string
	 */
	public function custom_archive_template($template)
	{
		if (is_post_type_archive('my-account')) {
			$user_template = WICKET_ACC_USER_TEMPLATE_PATH . 'account-centre/archive-wicket_acc.php';
			$plugin_template = WICKET_ACC_PLUGIN_TEMPLATE_PATH . 'account-centre/archive-wicket_acc.php';

			if (file_exists($user_template)) {
				return $user_template;
			} elseif (file_exists($plugin_template)) {
				return $plugin_template;
			}
		}

		return $template;
	}

	/**
	 * Check if current page is WooCommerce my-account page
	 *
	 * @return bool
	 */
	public function is_woocommerce_myaccount_page()
	{
		return function_exists('is_account_page') && is_account_page();
	}

	/**
	 * Redirect from /wc-account/ to /my-account/
	 *
	 * @return void
	 */
	public function redirect_woocommerce_myaccount()
	{
		if ($this->is_woocommerce_myaccount_page()) {
			$current_url = add_query_arg(null, null);
			$new_url = str_replace('/wc-account/', '/my-account/', $current_url);
			wp_safe_redirect($new_url);
			exit;
		}
	}
}
