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
		add_action('init', [$this, 'acc_custom_rewrite_rules'], 10, 0);
		add_filter('post_type_link', [$this, 'acc_remove_cpt_slug'], 10, 2);
		add_action('parse_request', [$this, 'handle_acc_request'], 1);
		add_filter('wpml_ls_language_url', [$this, 'fix_wpml_language_switcher_url'], 10, 2);
	}

	/**
	 * Get ACC page ID
	 *
	 * @return int
	 */
	public function get_acc_page_id()
	{
		if ($this->acc_page_id_cache === null) {
			$this->acc_page_id_cache = get_field('acc_page_account-centre', 'option');
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
		if (get_field('acc_page_' . $slug, 'option')) {
			$page_id = $this->get_page_id_by_slug($slug);

			return $this->get_page_id_by_slug($slug);
		}

		$page_id = $this->get_page_id_by_slug($slug);

		if ($page_id) {
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
				"SELECT ID FROM $wpdb->posts WHERE post_name = %s AND (post_type = 'wicket_acc') AND post_status = 'publish'",
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

		$this->maybe_create_main_acc_page();
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

			// Do we have translations for this page?
			if (function_exists('wpml_get_active_languages_filter')) {
				global $wpdb;

				$languages = apply_filters('wpml_active_languages', null, 'orderby=id&order=desc');
				$translation_found = false;
				foreach ($languages as $language) {
					$language_code = $language['code'];

					if ($language_code !== $wpdb->get_var("SELECT language_code FROM {$wpdb->prefix}icl_translations WHERE element_id = $woo_my_account_page_id AND element_type = 'post_page'")) {
						$translated_page_id = apply_filters('wpml_object_id', $woo_my_account_page_id, 'page', false, $language_code);

						if ($translated_page_id) {
							$wpdb->update($wpdb->posts, ['post_name' => $set_slug], ['ID' => $translated_page_id]);

							delete_post_meta($translated_page_id, '_wp_old_slug');

							$translation_found = true;
						}
					}
				}

				// Flush rewrite rules
				if ($translation_found) {
					flush_rewrite_rules();
				}
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
	public function acc_custom_rewrite_rules()
	{
		if (is_admin()) {
			return;
		}

		$wc_endpoints = WC()->query->get_query_vars();
		$default_acc_slug = $this->get_translated_acc_slug();

		// Get all wicket_acc posts
		$wicket_acc_posts = get_posts([
			'post_type' => 'wicket_acc',
			'post_status' => 'publish',
			'numberposts' => -1,
		]);

		$add_rules = function ($lang_code = '') use ($wc_endpoints, $default_acc_slug, $wicket_acc_posts) {
			$acc_slug = $lang_code ? $this->get_translated_acc_slug($lang_code) : $default_acc_slug;
			$lang_prefix = $lang_code ? $lang_code . '/' : '';

			// Add rule for the main account page
			add_rewrite_rule(
				"^{$lang_prefix}{$acc_slug}/?$",
				"index.php?post_type=wicket_acc&pagename={$acc_slug}" . ($lang_code ? "&lang={$lang_code}" : ""),
				'top'
			);

			// Add rules for each wicket_acc post
			foreach ($wicket_acc_posts as $post) {
				$post_slug = $post->post_name;
				add_rewrite_rule(
					"^{$lang_prefix}{$acc_slug}/{$post_slug}/?$",
					"index.php?post_type=wicket_acc&pagename={$post_slug}" . ($lang_code ? "&lang={$lang_code}" : ""),
					'top'
				);

				// Register each wicket_acc post slug as a WooCommerce endpoint
				add_rewrite_endpoint($post_slug, EP_ROOT | EP_PAGES);
			}

			// Add rules for WooCommerce endpoints
			foreach ($wc_endpoints as $key => $value) {
				add_rewrite_rule(
					"^{$lang_prefix}{$acc_slug}/{$value}(/(.*))?/?$",
					"index.php?post_type=wicket_acc&pagename={$acc_slug}&{$key}=\$matches[2]" . ($lang_code ? "&lang={$lang_code}" : ""),
					'top'
				);
			}
		};

		if (defined('ICL_SITEPRESS_VERSION')) {
			$current_language = apply_filters('wpml_current_language', null);
			$add_rules($current_language);
		} else {
			$add_rules();
		}

		// Flush rewrite rules to ensure new endpoints are registered
		flush_rewrite_rules();
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
	 * Remove CPT slug from permalink
	 *
	 * @param string $post_link The permalink of the post
	 * @param WP_Post $post The post object
	 * @return string The modified permalink
	 */
	public function acc_remove_cpt_slug($post_link, $post)
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
	 *
	 * @param WP $wp
	 *
	 * @return void
	 */
	public function handle_acc_request($wp)
	{
		if (is_admin()) {
			return;
		}

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

		if (isset($path_parts[0]) && $path_parts[0] === $acc_slug) {
			$slug = isset($path_parts[1]) && $path_parts[1] !== '' ? $path_parts[1] : $acc_slug;

			// Check WooCommerce endpoints
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

				// Use our custom template
				$template = $this->get_wicket_acc_template();
				if ($template) {
					add_filter('template_include', function () use ($template) {
						return $template;
					});
				}

				// Replace WooCommerce account navigation with custom sidebar
				remove_action('woocommerce_account_navigation', 'woocommerce_account_navigation', 10);
				add_action('woocommerce_account_navigation', [$this, 'acc_navigation'], 10);

				// Prevent further redirects
				add_filter('redirect_canonical', '__return_false');
				add_filter('woocommerce_get_endpoint_url', [$this, 'modify_wc_endpoint_url'], 10, 4);

				return;
			}

			// Check for pages at wicket_acc or page post type
			$page_id = $this->get_page_id_by_slug($slug);

			if (!$page_id && $slug === $acc_slug) {
				$page_id = $this->get_acc_page_id();
			}

			if (!$page_id) {
				$page_id = get_field('acc_page_' . $acc_slug, 'option');
			}

			if ($page_id) {
				// First, try to get a wicket_acc post type
				$wicket_acc_post = get_posts([
					'name' => $slug,
					'post_type' => 'wicket_acc',
					'post_status' => 'publish',
					'numberposts' => 1
				]);

				if (!empty($wicket_acc_post)) {
					$page_id = $wicket_acc_post[0]->ID;
					$post_type = 'wicket_acc';

					// Check for custom template
					$template = $this->get_wicket_acc_template();
					if ($template) {
						// Use the custom template
						add_filter('template_include', function () use ($template) {
							return $template;
						});
					}
				} else {
					$post_type = get_post_type($page_id);
				}

				$wp->query_vars['page_id'] = $page_id;
				$wp->query_vars['post_type'] = $post_type;
				$wp->query_vars['lang'] = $lang_code;
				set_query_var('wicket_acc', $slug);
				set_query_var('page_id', $page_id);

				// Replace WooCommerce account navigation with custom sidebar
				remove_action('woocommerce_account_navigation', 'woocommerce_account_navigation', 10);
				add_action('woocommerce_account_navigation', [$this, 'acc_navigation'], 10);

				// Prevent further redirects
				add_filter('redirect_canonical', '__return_false');

				return;
			}
		}

		// If we reach here, it means we couldn't find a matching page
		// We'll let WordPress handle the 404 error
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
			if ($value === $slug) {
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
		if (is_admin()) {
			return $url;
		}

		$acc_slug = $this->get_acc_slug();
		$current_lang = apply_filters('wpml_current_language', null);
		$default_lang = apply_filters('wpml_default_language', null);

		if ($current_lang !== $default_lang) {
			$translated_acc_slug = $this->get_translated_acc_slug($current_lang);
			$url = str_replace("/$acc_slug/", "/$translated_acc_slug/", $url);
		}

		// Remove duplicate slugs
		$url_parts = parse_url($url);
		$path = isset($url_parts['path']) ? $url_parts['path'] : '';
		$path_segments = array_filter(explode('/', $path));
		$unique_segments = array_unique($path_segments);
		$new_path = '/' . implode('/', $unique_segments);

		$url = str_replace($url_parts['path'], $new_path, $url);

		return $url;
	}

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

	private function get_wicket_acc_sidebar_template()
	{
		$user_template   = WICKET_ACC_USER_TEMPLATE_PATH . 'account-centre/sidebar.php';
		$plugin_template = WICKET_ACC_PLUGIN_TEMPLATE_PATH . 'account-centre/sidebar.php';

		if (file_exists($user_template)) {
			return $user_template;
		} elseif (file_exists($plugin_template)) {
			return $plugin_template;
		}

		return false;
	}

	public function acc_navigation()
	{
		$sidebar_template = $this->get_wicket_acc_sidebar_template();
		if ($sidebar_template) {
			include $sidebar_template;
		}
	}

	public function fix_wpml_language_switcher_url($url, $lang_code)
	{
		$current_lang = apply_filters('wpml_current_language', null);
		$default_lang = apply_filters('wpml_default_language', null);

		// Handle both string and array inputs for $lang_code
		$target_lang = is_array($lang_code) ? $lang_code['code'] : $lang_code;

		$current_acc_slug = $this->get_translated_acc_slug($current_lang);
		$target_acc_slug = $this->get_translated_acc_slug($target_lang);

		// Replace the current ACC slug with the target language ACC slug
		$url = str_replace("/$current_acc_slug/", "/$target_acc_slug/", $url);

		// Remove any duplicate occurrences of the ACC slug
		$url_parts = parse_url($url);
		$path = isset($url_parts['path']) ? $url_parts['path'] : '';
		$path_segments = array_filter(explode('/', $path));
		$unique_segments = array_values(array_unique($path_segments));
		$new_path = '/' . implode('/', $unique_segments);

		// Reconstruct the URL with the new path
		$scheme = isset($url_parts['scheme']) ? $url_parts['scheme'] . '://' : '';
		$host = isset($url_parts['host']) ? $url_parts['host'] : '';
		$query = isset($url_parts['query']) ? '?' . $url_parts['query'] : '';
		$fragment = isset($url_parts['fragment']) ? '#' . $url_parts['fragment'] : '';

		$new_url = trailingslashit($scheme . $host . $new_path . $query . $fragment);

		return $new_url;
	}
}
