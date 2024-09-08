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
 * 1. Update this plugin on target site. You will see a duplicated Account Centre CPT available. It's fine, don't panic.
 *
 * 2. On WPML Settings, set "my-account" CPT as Translatable.
 * /wp-admin/admin.php?page=tm/menu/settings
 *
 * 3. Load the secret migration URL on target site, at wp-admin/:
 * /wp-admin/?migrate_to_my_account=1_3
 *
 * 4. WPML Troubleshooting, do:
 * /wp-admin/admin.php?page=sitepress-multilingual-cms%2Fmenu%2Ftroubleshooting.php
 * 		> Set Language Information
 * 		> Fix terms count
 * 		> Fix post type assignments for translation
 *
 * 5. Flush rewrite rules (save WP Permalinks Settings).
 *
 * 6. On new "my-account" CPT, edit every FR page that shows as it was an EN page, one by one, and change their lang from EN to FR. Yes: EN to FR.
 * /wp-admin/edit.php?post_type=my-account
 * 	Language of this page: FR
 * 	Confirm on modal. Wait for reload.
 * 	Connect with translation, search for the correct EN page. Confirm.
 * 	On modal, UNCHECK "make FR the original language..." and click Assign.
 *
 * 7. Ammend every menu items with wrong URLs, from /account-centre/ to /my-account/
 * /wp-admin/nav-menus.php
 *
 * Done.
 *
 * If you need to translate my-account CPT slug, use WPML directly:
 * https://wpml.org/documentation/getting-started-guide/translating-page-slugs/
 */
class Router extends WicketAcc
{
	private $acc_page_id_cache = null;
	private $acc_slug_cache    = null;
	private $acc_url_cache     = null;

	/**
	 * Constructor
	 *
	 * @return void
	 */
	public function __construct()
	{
		// DEBUG ONLY, check environment
		if (defined('WP_ENV') && WP_ENV === 'development') {
			flush_rewrite_rules();
		}

		add_action('admin_init', [$this, 'init_all_pages']);
		add_action('admin_init', [$this, 'maybe_migrate_to_my_account']);
		add_action('init', [$this, 'acc_pages_template']);
		add_filter('archive_template', [$this, 'custom_archive_template']);
		add_action('plugins_loaded', [$this, 'redirect_acc_old_slugs']);
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

		$set_slug = 'dashboard';
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

		// Run this only when we manually add a secret query string to the URL
		if (!isset($_GET['migrate_to_my_account']) || $_GET['migrate_to_my_account'] !== '1_3') {
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
			global $wpdb;

			// Change page slug to wc-account and title to "WC Account"
			$query = $wpdb->prepare(
				"UPDATE $wpdb->posts SET post_name = 'wc-account', post_title = 'WC Account' WHERE ID = %d",
				$woo_my_account_page_id
			);

			$wpdb->query($query);

			// Delete old slug meta
			delete_post_meta($woo_my_account_page_id, '_wp_old_slug');
			delete_post_meta($woo_my_account_page_id, '_wp_old_date');

			// Remove any reference to "my-account" from WooCommerce
			delete_option('_woocommerce_myaccount_page_id');
			delete_option('_woocommerce_myaccount_page_slug');
			delete_option('_woocommerce_myaccount_page_title');

			// Set the new page as the WooCommerce my-account page
			update_option('_woocommerce_myaccount_page_id', $woo_my_account_page_id);
			update_option('_woocommerce_myaccount_page_slug', 'wc-account');
			update_option('_woocommerce_myaccount_page_title', 'WC Account');
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
					'ID'        => $acc_old_headerbanner,
					'post_name' => 'acc_global-headerbanner',
				]
			);

			// Update new ACF field
			update_field('acc_global-headerbanner', $acc_old_headerbanner, 'option');

			// Delete old ACF field
			delete_field('acc_global-banner', 'option');
		}

		// Flush rewrite rules, because we've changed the CPT slug
		flush_rewrite_rules();

		// Empty caches
		wp_cache_flush();

		// Save an option to track that we've changed the CPT to my-account
		update_option('wicket_acc_cpt_changed_to_my_account', true);

		// Show a message to the user
		wp_die('Migration to my-account completed. Continue with the next step.');
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
		global $wpdb;

		$wpdb->update($wpdb->posts, ['post_type' => 'my-account'], ['ID' => $post_id]);
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
			$acc_dashboard_id  = get_option('options_acc_page_dashboard');
			$acc_dashboard_url = get_permalink($acc_dashboard_id);

			wp_safe_redirect($acc_dashboard_url);
			exit;

			/*$user_template = WICKET_ACC_USER_TEMPLATE_PATH . 'account-centre/dashboard-wicket_acc.php';
			$plugin_template = WICKET_ACC_PLUGIN_TEMPLATE_PATH . 'account-centre/dashboard-wicket_acc.php';

			if (file_exists($user_template)) {
				return $user_template;
			} elseif (file_exists($plugin_template)) {
				return $plugin_template;
			}*/
		}

		return $template;
	}

	/**
	 * Redirect old slugs to /my-account/
	 */
	public function redirect_acc_old_slugs()
	{
		// Only if we already migrated to my-account
		if (!get_option('wicket_acc_cpt_changed_to_my_account')) {
			return;
		}

		$acc_dashboard_id  = get_option('options_acc_page_dashboard');
		$acc_dashboard_url = get_permalink($acc_dashboard_id);

		// WooCommerce account page
		if (str_contains($_SERVER['REQUEST_URI'], 'wc-account')) {
			// Check if the URL contains URLs like: /wc-account/org, /wc-account/organization/, /wc-account/organization-management/, etc. Anything after /wc-account/ that begins with "org"
			if (!preg_match('#^/wc-account/org#', $_SERVER['REQUEST_URI'])) {
				// Redirect to the new URL. Keep sub-folders (like /wc-account/orders/) and query params, also past POST data if any.
				$new_url = $acc_dashboard_url;

				// Keep sub-folders
				$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
				$subfolder = str_replace('/wc-account', '',
						$path
					);
				if ($subfolder) {
					$new_url = rtrim($new_url, '/') . $subfolder;
				}

				// Keep query parameters
				$query = parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY);
				if ($query) {
					$new_url .= '?' . $query;
				}

				// Redirect with POST data if any
				if ($_POST) {
					echo '<form id="redirect_form" method="post" action="' . esc_url($new_url) . '">';
					foreach ($_POST as $key => $value) {
						echo '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '">';
					}
					echo '</form>';
					echo '<script>document.getElementById("redirect_form").submit();</script>';
				} else {
					wp_safe_redirect($new_url);
				}

				exit;
			}
		}

		// Get old slugs
		$old_slugs = [
			'/account-centre',
			'/account-center',
		];

		// If requested URL contains any of the old slugs,
		foreach ($old_slugs as $old_slug) {
			if (str_contains($_SERVER['REQUEST_URI'], $old_slug)) {
				wp_safe_redirect($acc_dashboard_url);
				exit;
			}
		}
	}
}
