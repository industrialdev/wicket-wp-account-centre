<?php

namespace WicketAcc;

// No direct access
defined('ABSPATH') || exit;

/**
 * Router Class
 * Get ACC pages, IDs, slugs, and data needed to jump between them.
 * Also implements hexbit/router library.
 */
class Router extends WicketAcc
{
	private array $acc_pages_map = [
		'edit-profile'                   => 'Edit Profile',
		'events'                         => 'My Events',
		'jobs'                           => 'My Jobs',
		'job-post'                       => 'Post a Job',
		'change-password'                => 'Change Password',
		'organization-management'        => 'Organization Management',
	];

	/**
	 * Constructor
	 */
	public function __construct()
	{
		// DEBUG ONLY
		//flush_rewrite_rules();

		add_action('admin_init', [$this, 'init_all_pages']);
		add_action('admin_init', [$this, 'maybe_create_main_acc_page']);
		add_action('init', [$this, 'wicket_acc_custom_rewrite_rules'], 10, 0);
		add_action('template_redirect', [$this, 'redirect_myaccount_to_acc']);
		add_filter('post_type_link', [$this, 'wicket_acc_remove_cpt_slug'], 10, 2);
		add_action('parse_request', [$this, 'handle_acc_request'], 1);
		add_filter('redirect_canonical', [$this, 'prevent_acc_redirect'], 10, 2);
		add_action('init', [$this, 'register_acc_slug_for_translation'], 11);
		add_action('template_redirect', [$this, 'maybe_redirect_trailing_slash'], 1);
		add_action('template_redirect', [$this, 'redirect_duplicate_acc_slugs'], 1);
		add_action('template_redirect', [$this, 'handle_404'], 99);
		add_action('template_redirect', [$this, 'redirect_woocommerce_endpoints']);
	}

	/**
	 * Get ACC page ID
	 *
	 * @return int
	 */
	public function get_acc_page_id()
	{
		$acc_page_id = get_field('acc_page_account-centre', 'option');

		return $acc_page_id;
	}

	/**
	 * Get ACC page Slug
	 *
	 * @return string
	 */
	public function get_acc_slug()
	{
		$acc_page_id   = $this->get_acc_page_id();
		$acc_page_slug = get_post($acc_page_id)->post_name;

		return $acc_page_slug;
	}

	/**
	 * Get ACC Page URL
	 *
	 * @return string
	 */
	public function get_acc_url()
	{
		$acc_page_id  = $this->get_acc_page_id();
		$acc_page_url = get_permalink($acc_page_id);

		return $acc_page_url;
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
		if (get_field('acc_page_' . $slug, 'option')) {
			$page_id = $this->get_page_id_by_slug($slug);
			$this->set_post_type($page_id);

			return $this->get_page_id_by_slug($slug);
		}

		$page_id = $this->get_page_id_by_slug($slug);

		if ($page_id) {
			$this->set_post_type($page_id);
			update_field('acc_page_' . $slug, $page_id, 'option');

			return $page_id;
		}

		// Create requested page as a child of ACC index page
		$page_id = wp_insert_post(
			[
				'post_type'      => 'wicket_acc',
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
	 * Check if page exists, by slug or ID
	 *
	 * @param string|int $slug_or_id
	 *
	 * @return bool True if page exists, false if not
	 */
	public function page_exists($slug_or_id)
	{
		if (empty($slug_or_id)) {
			return false;
		}

		if (!is_numeric($slug_or_id)) {
			$page_id = $this->get_page_id_by_slug($slug_or_id);
		}

		if (is_numeric($slug_or_id)) {
			// Check if page ID exists in the database
			if (!get_post($page_id)) {
				return false;
			}

			$page_id = absint($slug_or_id);
		}

		if ($page_id) {
			return true;
		}

		return false;
	}

	/**
	 * Set post_type of post/page to 'wicket_acc'
	 *
	 * @param string $post_id Post ID
	 *
	 * @return bool
	 */
	public function set_post_type($post_id)
	{
		$post_type = get_post_type($post_id);

		if ($post_type == 'page' || $post_type == 'post') {
			// Set post type to 'wicket_acc'
			wp_update_post(
				[
					'ID'          => $post_id,
					'post_type'   => 'wicket_acc',
					'post_status' => 'publish',
				]
			);

			return true;
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
				"SELECT ID FROM $wpdb->posts WHERE post_name = %s AND (post_type = 'wicket_acc' OR post_type = 'page') AND post_status = 'publish'",
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
	}

	/**
	 * Maybe create ACC page
	 *
	 * return void
	 */
	public function maybe_create_main_acc_page()
	{
		$set_slug = WACC()->get_slug();
		$set_name = WACC()->get_name();

		// Empty?
		if (empty($set_slug) || empty($set_name)) {
			return;
		}

		// Get WooCommerce my-account page ID
		$woo_my_account_page_id = wc_get_page_id('myaccount');

		if ($woo_my_account_page_id > 0) {
			// Get page slug
			$woo_my_account_page_slug = get_post($woo_my_account_page_id)->post_name;

			// Check if slug don't end with '-woo'
			if (!str_ends_with($woo_my_account_page_slug, '-woo')) {
				global $wpdb;

				// SQL query to rename page slug to {slug}-woo. We want to avoid WP triggering a redirect to the new slug.
				$wpdb->update($wpdb->posts, ['post_name' => $set_slug . '-woo'], ['ID' => $woo_my_account_page_id]);

				// Delete meta _wp_old_slug
				delete_post_meta($woo_my_account_page_id, '_wp_old_slug');

				// Flush rewrite rules
				flush_rewrite_rules();
			}
		}

		// Query wicket_acc CPT an check if get already have one with set_slug
		$args = [
			'name'           => $set_slug,
			'post_type'      => 'wicket_acc',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
		];

		$post_acc = get_posts($args);

		if ($post_acc) {
			// We already have a page with this slug
			return;
		}

		// Create page
		$this->create_page($set_slug, $set_name);
	}

	/**
	 * Custom rewrite rules for ACC
	 *
	 * @return void
	 */
	public function wicket_acc_custom_rewrite_rules()
	{
		$wc_endpoints = WC()->query->get_query_vars();

		if (defined('ICL_SITEPRESS_VERSION')) {
			$languages = apply_filters('wpml_active_languages', null, 'orderby=id&order=desc');

			foreach ($languages as $lang_code => $language) {
				$acc_slug = $this->get_translated_acc_slug($lang_code);

				add_rewrite_rule(
					"^{$lang_code}/{$acc_slug}/?$",
					"index.php?post_type=wicket_acc,page&pagename={$acc_slug}&lang={$lang_code}",
					'top'
				);
				add_rewrite_rule(
					"^{$lang_code}/{$acc_slug}/([^/]+)/?$",
					"index.php?post_type=wicket_acc,page&pagename=\$matches[1]&lang={$lang_code}",
					'top'
				);

				foreach ($wc_endpoints as $key => $value) {
					add_rewrite_rule(
						"^{$lang_code}/{$acc_slug}/{$value}(/(.*))?/?$",
						"index.php?post_type=wicket_acc,page&pagename={$acc_slug}&{$key}=\$matches[2]&lang={$lang_code}",
						'top'
					);
				}
			}
		}

		// Add rules for the default language or when WPML is not active
		$default_acc_slug = $this->get_translated_acc_slug();
		add_rewrite_rule(
			"^{$default_acc_slug}/?$",
			"index.php?post_type=wicket_acc,page&pagename={$default_acc_slug}",
			'top'
		);
		add_rewrite_rule(
			"^{$default_acc_slug}/([^/]+)/?$",
			"index.php?post_type=wicket_acc,page&pagename=\$matches[1]",
			'top'
		);

		foreach ($wc_endpoints as $key => $value) {
			add_rewrite_rule(
				"^{$default_acc_slug}/{$value}(/(.*))?/?$",
				"index.php?post_type=wicket_acc,page&pagename={$default_acc_slug}&{$key}=\$matches[2]",
				'top'
			);
		}
	}

	/**
	 * Get translated ACC slug
	 *
	 * @param string $language
	 *
	 * @return string
	 */
	private function get_translated_acc_slug($language = null)
	{
		$acc_slug = $this->get_acc_slug();

		if (defined('ICL_SITEPRESS_VERSION')) {
			// Get the post ID of the main account page
			$acc_page_id = $this->get_acc_page_id();

			// Get the translated post ID
			$translated_page_id = apply_filters('wpml_object_id', $acc_page_id, 'wicket_acc', false, $language);

			if ($translated_page_id) {
				// Get the slug of the translated page
				$translated_post = get_post($translated_page_id);
				if ($translated_post) {
					return $translated_post->post_name;
				}
			}

			// If no translation found, use WPML's string translation
			$translated_slug = apply_filters('wpml_translate_single_string', $acc_slug, 'WordPress', 'URL slug: wicket_acc', $language);
			return $translated_slug !== null && $translated_slug !== '' ? $translated_slug : $acc_slug;
		}

		return $acc_slug;
	}

	/**
	 * Redirect WooCommerce My Account page to Wicket Account Centre
	 */
	public function redirect_myaccount_to_acc()
	{
		// Check if we're on the WooCommerce My Account page
		if (is_page(wc_get_page_id('myaccount'))) {
			// Check if there's a WooCommerce endpoint in the URL
			$current_endpoint = WC()->query->get_current_endpoint();

			// Don't redirect if there's a WooCommerce endpoint
			if ($current_endpoint) {
				return;
			}

			// Construct the redirect URL
			$redirect_url = $this->get_acc_url();

			// Perform the redirect
			wp_safe_redirect($redirect_url);
			exit;
		}
	}

	/**
	 * Remove CPT slug from permalink
	 *
	 * @param string $post_link The permalink of the post
	 * @param WP_Post $post The post object
	 * @return string The modified permalink
	 */
	public function wicket_acc_remove_cpt_slug($post_link, $post)
	{
		if ('wicket_acc' === $post->post_type && 'publish' === $post->post_status) {
			$language = apply_filters('wpml_current_language', null);
			$translated_slug = $this->get_translated_acc_slug($language);
			return str_replace('/wicket_acc/', '/' . $translated_slug . '/', $post_link);
		}
		return $post_link;
	}

	/**
	 * Handle ACC request
	 */
	public function handle_acc_request($wp)
	{
		$request_uri = trim($wp->request, '/');
		$path_parts = explode('/', $request_uri);

		$lang_code = '';
		if (function_exists('wpml_get_active_languages_filter')) {
			$active_languages = apply_filters('wpml_active_languages', null, 'skip_missing=0&orderby=code');
			if (isset($active_languages[$path_parts[0]])) {
				$lang_code = array_shift($path_parts);
			}
		}

		$acc_slug = $this->get_translated_acc_slug($lang_code);
		$acc_slug_woo = $acc_slug . '-woo';

		if (isset($path_parts[0]) && ($path_parts[0] === $acc_slug || $path_parts[0] === $acc_slug_woo)) {
			$slug = isset($path_parts[1]) && $path_parts[1] !== '' ? $path_parts[1] : $acc_slug;

			// Check WooCommerce endpoints first
			$wc_endpoint = $this->get_woocommerce_endpoint($slug);
			if ($wc_endpoint) {
				$wp->query_vars['page'] = '';
				$wp->query_vars['post_type'] = '';
				$wp->query_vars[$wc_endpoint] = isset($path_parts[2]) ? $path_parts[2] : '';

				// Set the endpoint for WooCommerce
				if (method_exists(WC()->query, 'set_current_endpoint')) {
					WC()->query->set_current_endpoint($wc_endpoint);
				} else {
					// Fallback: manually set the query var
					set_query_var($wc_endpoint, isset($path_parts[2]) ? $path_parts[2] : '');
				}

				// Set the page_id to the WooCommerce My Account page
				$wp->query_vars['page_id'] = wc_get_page_id('myaccount');

				// Prevent further redirects
				add_filter('redirect_canonical', '__return_false');
				add_filter('woocommerce_get_endpoint_url', [$this, 'modify_wc_endpoint_url'], 10, 4);

				return;
			}

			// If not a WooCommerce endpoint, proceed with the existing logic
			$page_id = $this->get_page_id_by_slug($slug);

			if (!$page_id && $slug === $acc_slug) {
				$page_id = $this->get_acc_page_id();
			}

			if (!$page_id) {
				$page_id = get_field('acc_page_' . $acc_slug, 'option');
			}

			if ($page_id) {
				$post_type = get_post_type($page_id);
				$wp->query_vars['page_id'] = $page_id;
				$wp->query_vars['post_type'] = $post_type;
				$wp->query_vars['lang'] = $lang_code;
				set_query_var('wicket_acc', $slug);
				set_query_var('page_id', $page_id);

				// Prevent further redirects
				add_filter('redirect_canonical', '__return_false');

				return;
			}
		}

		// If we reach here, it means we couldn't find a matching page
		// We'll let WordPress handle the 404 error
	}

	/**
	 * Prevent default WordPress redirect for ACC pages
	 */
	public function prevent_acc_redirect($redirect_url, $requested_url)
	{
		$acc_slug = $this->get_acc_slug();

		if (str_contains($requested_url, $acc_slug)) {
			// Check if there's a WooCommerce endpoint in the URL
			$current_endpoint = WC()->query->get_current_endpoint();

			if ($current_endpoint) {
				return false; // Prevent redirect for WooCommerce endpoints
			}

			// Allow WordPress to handle trailing slashes
			return $redirect_url;
		}

		return $redirect_url;
	}

	/**
	 * Register 'wicket_acc' slug for translation
	 */
	public function register_acc_slug_for_translation()
	{
		if (function_exists('wpml_register_single_string')) {
			$acc_slug = $this->get_acc_slug();
			wpml_register_single_string('WordPress', 'URL slug: wicket_acc', $acc_slug);

			// Also register the string for WPML String Translation
			do_action('wpml_register_string', $acc_slug, 'URL slug: wicket_acc', 'WordPress', 'URL slug', 1);
		}
	}

	/**
	 * Redirect to the page with a trailing slash
	 *
	 * @return void
	 */
	public function maybe_redirect_trailing_slash()
	{
		if (is_admin() || wp_doing_ajax() || wp_is_json_request() || (defined('REST_REQUEST') && REST_REQUEST)) {
			return;
		}

		if (!get_option('permalink_structure')) {
			return;
		}

		$request_uri = $_SERVER['REQUEST_URI'];

		// Ignore URLs with query parameters
		if (strpos($request_uri, '?') !== false) {
			return;
		}

		// Ignore URLs with file extensions
		if (pathinfo($request_uri, PATHINFO_EXTENSION)) {
			return;
		}

		// Ignore the root URL
		if ($request_uri === '/') {
			return;
		}

		if (substr($request_uri, -1) !== '/') {
			$redirect_url = trailingslashit($request_uri);
			wp_safe_redirect($redirect_url, 301);
			exit;
		}
	}

	/**
	 * Redirect duplicate ACC slugs
	 */
	public function redirect_duplicate_acc_slugs()
	{
		$request_uri = $_SERVER['REQUEST_URI'];
		$path_parts = explode('/', trim($request_uri, '/'));

		if (count($path_parts) < 2) {
			return;
		}

		$lang_code = '';
		if (function_exists('wpml_get_active_languages_filter')) {
			$active_languages = apply_filters('wpml_active_languages', null, 'skip_missing=0&orderby=code');
			if (isset($active_languages[$path_parts[0]])) {
				$lang_code = array_shift($path_parts);
			}
		}

		$acc_slug = $this->get_translated_acc_slug($lang_code);

		if ($path_parts[0] === $acc_slug && isset($path_parts[1]) && $path_parts[1] === $acc_slug) {
			$redirect_url = '/' . ($lang_code ? $lang_code . '/' : '') . $acc_slug . '/';
			wp_safe_redirect($redirect_url, 301);
			exit;
		}
	}

	/**
	 * Handle 404 errors
	 */
	public function handle_404()
	{
		if (is_404()) {
			$this->handle_acc_request($GLOBALS['wp']);
		}
	}

	/**
	 * Get WooCommerce endpoint
	 *
	 * @param string $endpoint
	 * @return string|bool The query var key for the endpoint or false if not found
	 */
	private function get_woocommerce_endpoint($slug)
	{
		if (!function_exists('WC')) {
			return false;
		}

		$wc_query = WC()->query;
		if (!$wc_query) {
			return false;
		}

		$query_vars = $wc_query->get_query_vars();
		foreach ($query_vars as $key => $value) {
			if ($value === $slug || $value === $slug . '-woo') {
				return $key;
			}
		}

		return false;
	}

	/**
	 * Modify WooCommerce endpoint URLs
	 *
	 * @param string $url The endpoint URL
	 * @param string $endpoint The endpoint name
	 * @param string $value The endpoint value
	 * @param string $permalink The permalink
	 * @return string The modified endpoint URL
	 */
	public function modify_wc_endpoint_url($url, $endpoint, $value, $permalink)
	{
		$acc_slug = $this->get_acc_slug();
		$acc_slug_woo = $acc_slug . '-woo';

		if (strpos($url, $acc_slug_woo) !== false) {
			$url = str_replace($acc_slug_woo, $acc_slug, $url);
		}

		return $url;
	}

	/**
	 * Redirect WooCommerce endpoints to the ACC
	 */
	public function redirect_woocommerce_endpoints()
	{
		$acc_slug = $this->get_acc_slug();
		$acc_slug_woo = $acc_slug . '-woo';
		$current_url = $_SERVER['REQUEST_URI'];

		if (strpos($current_url, $acc_slug_woo) !== false) {
			$new_url = str_replace($acc_slug_woo, $acc_slug, $current_url);
			wp_safe_redirect($new_url);
			exit;
		}
	}
}
