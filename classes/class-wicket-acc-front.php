<?php

namespace WicketAcc;

// No direct access
defined('ABSPATH') || exit;

/**
 * Front file of Module
 *
 * Manage all actions of front.
 *
 * @package  wicket-account-centre
 * @version  1.0.0
 */

/**
 * Front class of module.
 */
class Front extends WicketAcc
{
	/**
	 * Constructor.
	 */
	public function __construct()
	{
		add_action('wp_enqueue_scripts', [$this, 'front_assets']);
		add_action('init', [$this, 'add_endpoints_and_content'], 2050); // High priority for WPML compatibility
		add_filter('woocommerce_get_query_vars', [$this, 'custom_query_vars'], 1);
		add_filter('woocommerce_account_menu_items', [$this, 'custom_my_account_menu_items'], 2050); // High priority for WPML compatibility

		// Intercept templates for WP CPT: wicket_acc
		add_filter('template_include', [$this, 'intercept_cpt_template'], 99);

		// Intercept templates for WC
		add_action('woocommerce_locate_template', [$this, 'intercept_wc_template'], 10, 3);

		add_filter('the_title', [$this, 'custom_endpoint_titles'], 10, 2);
		add_filter('the_title', [$this, 'core_endpoint_titles'], 10, 2);
	}

	/**
	 * Enqueue scripts for front.
	 */
	public function front_assets()
	{
		if (is_admin()) {
			return;
		}

		// Legacy javascript
		wp_enqueue_script('wicket-acc-front', WICKET_ACC_URL . 'assets/js/wicket-acc-legacy.js', false, '1.0', true);

		// Load the block styles
		wp_enqueue_style('wicket-acc', WICKET_ACC_URL . 'assets/css/wicket-acc.css', false, WICKET_ACC_VERSION);

		// Load the block scripts
		wp_enqueue_script('wicket-acc', WICKET_ACC_URL . 'assets/js/wicket-acc.js', false, WICKET_ACC_VERSION, true);
	}

	/**
	 * Get endpoints.
	 */
	public function get_endpoints()
	{
		$wicket_acc_custom_dashboard_id = get_option('wicket_acc_set_ep_custom_dashboard');

		$args = [
			'numberposts' => -1,
			'post_type'   => 'wicket_acc',
			'post_status' => 'publish',
			'orderby'     => 'menu_order',
			'order'       => 'ASC',
			'suppress_filters' => false,
			'post__not_in' => [$wicket_acc_custom_dashboard_id], /* exclude custom dashboard endpoint */
		];

		$wicket_acc_eps = get_posts($args);

		return $wicket_acc_eps;
	}

	/**
	 * Change endpoint title for custom endpoints.
	 */
	public function custom_endpoint_titles($title, $id)
	{
		$wicket_acc_endpoints = $this->get_endpoints();
		if (is_array($wicket_acc_endpoints)) {
			foreach ($wicket_acc_endpoints as $wicket_endpoint) {
				global $wp_query;

				$ep_id                 = $wicket_endpoint->ID;
				$wicket_slug           = get_post_meta(intval($ep_id), 'wicket_acc_slug_fld', true);
				$wicket_acc_menu_title = get_post_meta(intval($ep_id), 'wicket_acc_menu_title', true);
				$is_endpoint           = isset($wp_query->query_vars[$wicket_slug]);

				if (($id == $wp_query->queried_object_id) && $is_endpoint && !is_admin() && is_main_query() && in_the_loop() && is_account_page()) {
					// New page title.
					$title = $wicket_acc_menu_title;

					remove_filter('the_title', 'custom_endpoint_titles');
				}
			}
		}

		return $title;
	}

	/**
	 * Change endpoint title for core WooCommerce endpoints.
	 *
	 * NEED TO ADD SETTINGS TO MANAGE THESE TITLES FROM ADMIN
	 */
	public function core_endpoint_titles($title, $id)
	{
		global $wp_query;

		if (($id == $wp_query->queried_object_id) && in_the_loop() && !is_admin()) {
			if (is_wc_endpoint_url('downloads')) { // add your endpoint urls
				$title = __("My Downloads", 'wicket-acc'); // change your entry-title
			} elseif (is_wc_endpoint_url('orders')) {
				$title = __("My Orders", 'wicket-acc');
			} elseif (is_wc_endpoint_url('edit-account')) {
				$title = __("Change My Details", 'wicket-acc');
			} elseif (is_wc_endpoint_url('edit-address')) {
				$title = __("My Addresses", 'wicket-acc');
			} elseif (is_wc_endpoint_url('payment-methods')) {
				$title = __("My Payment Methods", 'wicket-acc');
			} elseif (!is_wc_endpoint_url() && is_account_page()) {
				$title = __("My Account Centre", 'wicket-acc');
			}
		}

		return $title;
	}

	/**
	 * Endpoint contents.
	 */
	public function add_endpoints_and_content()
	{
		if (!empty(get_option('wicket_acc_set_ep_as_fld'))) {
			remove_action('woocommerce_account_navigation', 'woocommerce_account_navigation', 10);
			add_action('woocommerce_account_navigation', array($this, 'account_navigation'), 10);
		}

		$wicket_acc_endpoints = $this->get_endpoints();

		if (is_array($wicket_acc_endpoints)) {

			foreach ($wicket_acc_endpoints as $wicket_endpoint) {

				$ep_id       = $wicket_endpoint->ID;
				$wicket_slug = get_post_meta(intval($ep_id), 'wicket_acc_slug_fld', true);

				add_rewrite_endpoint($wicket_slug, EP_ROOT | EP_PAGES, $wicket_slug);

				add_action(
					'woocommerce_account_' . $wicket_slug . '_endpoint',
					function () use ($ep_id) {
						$this->get_custom_endpoint_content($ep_id);
					},
					5
				);
			}

			/**
			 * Do we really need to do this every single time, on every page load?!
			 * See https://developer.wordpress.org/reference/functions/flush_rewrite_rules/
			 */
			//flush_rewrite_rules();
		}
	}

	/**
	 * Replace sidebar (navigation)
	 */
	public function account_navigation()
	{
		// Check if file exists on child theme first
		if (file_exists(
			WICKET_ACC_USER_TEMPLATE_PATH . 'account-centre/sidebar.php'
		)) {
			include_once WICKET_ACC_USER_TEMPLATE_PATH . 'account-centre/sidebar.php';
		} else {
			include_once WICKET_ACC_PLUGIN_TEMPLATE_PATH . 'account-centre/sidebar.php';
		}
	}

	/**
	 * Register Query variables.
	 *
	 * @param array $vars Variables.
	 */
	public function custom_query_vars($vars)
	{

		$wicket_acc_endpoints = $this->get_endpoints();

		if (is_array($wicket_acc_endpoints)) {

			foreach ($wicket_acc_endpoints as $wicket_endpoint) {
				$ep_id              = $wicket_endpoint->ID;
				$wicket_slug        = get_post_meta(intval($ep_id), 'wicket_acc_slug_fld', true);
				$vars[$wicket_slug] = $wicket_slug;
			}
		}

		return $vars;
	}

	/**
	 * Add custom endpoints in nav menus.
	 *
	 * @param array $items Menu items.
	 */
	public function custom_my_account_menu_items($items)
	{
		$wicket_acc_endpoints = $this->get_endpoints();
		$hide_ep_list         = get_option('wicket_acc_set_ep_hide_fld');
		$curr_user            = wp_get_current_user();
		$user_data            = get_user_meta($curr_user->ID);
		$curr_user_role       = $curr_user->roles;

		// Remove the logout menu item.
		$logout = $items['customer-logout'];

		unset($items['customer-logout']);

		if (is_array($wicket_acc_endpoints)) {

			foreach ($wicket_acc_endpoints as $wicket_endpoint) {
				// Insert your custom endpoint.
				$ep_id           = $wicket_endpoint->ID;
				$wicket_slug     = get_post_meta(intval($ep_id), 'wicket_acc_slug_fld', true);
				$wicket_acc_icon = get_post_meta(intval($ep_id), 'wicket_acc_icon_fld', true);

				if (!empty($wicket_acc_icon)) {
					$wicket_acc_icon = '\\' . $wicket_acc_icon;
				}

				$wicket_acc_ep_type = get_post_meta(intval($ep_id), 'wicket_acc_endpType_fld', true);

				$wicket_acc_user_role = get_post_meta(intval($ep_id), 'wicket_acc_user_role', true);

				$wicket_acc_menu_title = get_post_meta(intval($ep_id), 'wicket_acc_menu_title', true);

				if (empty($wicket_acc_menu_title)) {
					$wicket_acc_menu_title = $wicket_endpoint->post_title;
				}

				if (is_user_logged_in()) {

					if (is_array($wicket_acc_user_role) || empty($wicket_acc_user_role)) {

						if (in_array($curr_user_role, (array)$wicket_acc_user_role, true) || empty($wicket_acc_user_role) || is_admin()) {

							$items[$wicket_slug] = wp_kses_post($wicket_acc_menu_title);
						}
					}
				}

				if (!is_user_logged_in()) {
					if (is_array($wicket_acc_user_role) && !empty($wicket_acc_user_role)) {
						if (in_array('guest', $wicket_acc_user_role, true) || is_admin()) {

							$items[$wicket_slug] = wp_kses_post($wicket_endpoint->post_title);
						}
					}
				}

				if (!empty($wicket_acc_icon) && !is_admin()) {
					if ('group_endpoint' === $wicket_acc_ep_type) {
?>
						<style type="text/css">
							.myaccount-nav ul li.nav-item--<?php echo esc_attr($wicket_slug); ?>a.group_endpoint_a::before {

								content: "<?php echo esc_attr($wicket_acc_icon); ?>";

							}
						</style>
					<?php
					} else {
					?>
						<style type="text/css">
							.myaccount-nav ul li.nav-item--<?php echo esc_attr($wicket_slug); ?>a::before {

								content: "<?php echo esc_attr($wicket_acc_icon); ?>";
							}
						</style>
<?php
					}
				}
			}
		}

		// Insert back the logout item.
		$items['customer-logout'] = $logout;

		if (!empty($hide_ep_list)) {
			foreach ($hide_ep_list as $h_ep) {
				unset($items[esc_attr($h_ep)]);
			}
		}

		$sorted_endpoints = get_option('wicket_acc_sorted_endponts');
		$child_sorting    = get_option('wicket_acc_sorted_child_endpoints');
		$sorted_items     = [];

		foreach ((array) $sorted_endpoints as $value) {
			if (isset($items[$value])) {
				$sorted_items[$value] = $items[$value];
			}

			if (isset($child_sorting[$value])) {
				foreach ((array) $child_sorting[$value] as $value1) {
					if (isset($items[$value1])) {
						$sorted_items[$value1] = $items[$value1];
					}
				}
			}
		}

		$sorted_items = array_unique(array_merge($sorted_items, $items));

		return $sorted_items;
	}

	/**
	 * Get custom content.
	 *
	 * @param int $id ID of menu item.
	 */
	public function get_custom_endpoint_content($id = '')
	{
		$ep_id              = $id;
		$wicket_acc_ep_type = get_post_meta(intval($ep_id), 'wicket_acc_endpType_fld', true);

		if ('cendpoint' === esc_attr($wicket_acc_ep_type)) {
			$post = get_post($ep_id); // specific post

			$the_content = apply_filters('the_content', $post->post_content);

			if (!empty($the_content)) {
				echo $the_content;
			}

			return;
		}
	}

	/**
	 * Filter the woocommerce template path to use this plugin instead of the one in WooCommerce or theme.
	 *
	 * @param string $template      Default template file path.
	 * @param string $template_name Template file slug.
	 * @param string $template_path Template file name.
	 *
	 * @return string The new Template file path.
	 */
	public function intercept_wc_template($template, $template_name, $template_path)
	{
		if ('dashboard.php' === basename($template)) {
			// Check if file exists on child theme first
			if (file_exists(
				WICKET_ACC_USER_TEMPLATE_PATH . 'account-centre/dashboard.php'
			)) {
				$template = WICKET_ACC_USER_TEMPLATE_PATH . 'account-centre/dashboard.php';
			} else {
				$template = WICKET_ACC_PLUGIN_TEMPLATE_PATH . 'account-centre/dashboard.php';
			}
		}

		if ('my-account.php' === basename($template)) {
			// Check if file exists on child theme first
			if (file_exists(
				WICKET_ACC_USER_TEMPLATE_PATH . 'account-centre/my-account.php'
			)) {
				$template = WICKET_ACC_USER_TEMPLATE_PATH . 'account-centre/my-account.php';
			} else {
				$template = WICKET_ACC_PLUGIN_TEMPLATE_PATH . 'account-centre/my-account.php';
			}
		}

		return $template;
	}

	/**
	 * Intercept single templates for CPT: wicket_acc
	 *
	 * @param string $single Default template file path.
	 *
	 * @return string The new Template file path.
	 */
	public function intercept_cpt_template($single)
	{
		global $post;

		// Check if we're on a single wicket_acc post or if the current request is for the account centre
		if (is_singular('wicket_acc') || $this->is_acc_request()) {
			// WooCommerce endpoint being loaded?
			if (is_wc_endpoint_url()) {
				return $single;
			}

			// Define the paths to your custom templates
			$user_custom_template = WICKET_ACC_USER_TEMPLATE_PATH . 'account-centre/page-wicket_acc.php';
			$plugin_custom_template = WICKET_ACC_PLUGIN_TEMPLATE_PATH . 'account-centre/page-wicket_acc.php';

			// First, check if the user-defined custom template exists
			if (file_exists($user_custom_template)) {
				return $user_custom_template;
			}
			// If not, fall back to the plugin-defined custom template
			elseif (file_exists($plugin_custom_template)) {
				return $plugin_custom_template;
			}
		}

		// Return the default template if not a wicket_acc post or if neither custom template exists
		return $single;
	}

	private function is_acc_request()
	{
		$current_url = add_query_arg(null, null);
		$acc_slug    = WACC()->Router->get_acc_slug();
		return strpos($current_url, "/$acc_slug/") !== false;
	}
}
