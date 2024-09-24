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
 * 1. Open wp-admin at the target site being updated. Reload it if you are already there.
 *
 * 2. Follow on-screen instructions. They will be only be shown once.
 *
 * Done.
 *
 * If you need to translate my-account CPT slug for other languages, use WPML directly:
 * https://wpml.org/documentation/getting-started-guide/translating-page-slugs/
 */
class Router extends WicketAcc
{
	private $acc_page_id_cache = null;
	private $acc_slug_cache    = null;

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
		add_action('init', [$this, 'acc_pages_template']);
		add_filter('archive_template', [$this, 'custom_archive_template']);
		add_action('plugins_loaded', [$this, 'acc_redirects']);
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
		// Check if we've already created the main page
		if (get_option('wicket_acc_created_dashboard_page')) {
			return;
		}

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
	 * Get Wicket ACC template
	 *
	 * @return string|false
	 */
	private function get_wicket_acc_template()
	{
		$user_template = WICKET_ACC_USER_TEMPLATE_PATH . 'account-centre/page-acc.php';
		$plugin_template = WICKET_ACC_PLUGIN_TEMPLATE_PATH . 'account-centre/page-acc.php';

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
		if (is_admin()) {
			return;
		}

		add_filter('single_template', function ($single_template) {
			global $post;

			if (is_admin()) {
				return $single_template;
			}

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
	 * NOTE: we used to redirect users earlier, at template_redirect. But, WP complains on some server configurations, that you can't access is_post_type_archive function before the main query is run. So, to avoid issues with those sites, we're using a custom archive template and redirecting users over there.
	 *
	 * @param string $template
	 *
	 * @return string
	 */
	public function custom_archive_template($template)
	{
		if (is_admin()) {
			return $template;
		}

		if (is_post_type_archive('my-account')) {
			$fixed_template = WICKET_ACC_PATH . 'includes/archive-acc.php';

			if (file_exists($fixed_template)) {
				return $fixed_template;
			}
		}

		return $template;
	}

	/**
	 * Redirects for ACC
	 * 1. /wc-account/ index only to /my-account/dashboard/
	 * 2. Old acc slugs (account-centre) to new slugs (my-account)
	 * 3. WooCommerce critical endpoints out from ACC
	 */
	public function acc_redirects()
	{
		if (is_admin()) {
			return;
		}

		// Only if we already migrated to my-account
		if (!get_option('wicket_acc_cpt_changed_to_my_account')) {
			return;
		}

		$acc_dashboard_id  = get_option('options_acc_page_dashboard');
		$acc_dashboard_url = get_permalink($acc_dashboard_id);

		$wc_page_id   = wc_get_page_id('myaccount');
		$wc_page_slug = get_post($wc_page_id)->post_name;

		// WooCommerce account page index only
		if (str_contains($_SERVER['REQUEST_URI'], $wc_page_slug)) {
			// Redirect user when is on WC index page only
			if ($_SERVER['REQUEST_URI'] === '/' . $wc_page_slug . '/') {
				if (headers_sent()) {
					// Any other more elegant way to do this?
					echo '<meta http-equiv="refresh" content="0;url=' . $acc_dashboard_url . '" />';
					echo '<script>window.location.href="' . $acc_dashboard_url . '";</script>';
				} else {
					wp_safe_redirect($acc_dashboard_url);
				}
				exit;
			}
		}

		// Redirect old ACC slugs
		$acc_old_slugs = [
			'/account-centre',
			'/account-center',
		];

		foreach ($acc_old_slugs as $old_slug) {
			// If requested URL contains any of the old slugs,
			if (str_contains($_SERVER['REQUEST_URI'], $old_slug)) {
				if (headers_sent()) {
					// Any other more elegant way to do this?
					echo '<meta http-equiv="refresh" content="0;url=' . $acc_dashboard_url . '" />';
					echo '<script>window.location.href="' . $acc_dashboard_url . '";</script>';
				} else {
					wp_safe_redirect($acc_dashboard_url);
				}
				exit;
			}
		}

		// Redirect (some) WC endpoints
		// There're some WC endpoints that need to be loaded from WC directly, and can't be easily replaced with my-account posts.
		$wc_forced_endpoints = [
			'add-payment-method',
		];

		foreach ($wc_forced_endpoints as $endpoint) {
			// Are we already inside any WC endpoint?
			if (str_contains($_SERVER['REQUEST_URI'], $wc_page_slug)) {
				// We don't want an endless loop
				break;
			}

			// If requested URL contains a critical WC endpoint
			if (str_contains($_SERVER['REQUEST_URI'], $endpoint)) {
				// Get the "Add Payment Method" URL
				$wc_endpoint_url = home_url(wc_get_endpoint_url($endpoint, '', $wc_page_slug));

				if (headers_sent()) {
					// Any other more elegant way to do this?
					echo '<meta http-equiv="refresh" content="0;url=' . $wc_endpoint_url . '" />';
					echo '<script>window.location.href="' . $wc_endpoint_url . '";</script>';
				} else {
					wp_safe_redirect($wc_endpoint_url);
				}
				exit;
			}
		}
	}
}
