<?php

namespace WicketAcc;

// No direct access
defined('ABSPATH') || exit;

/**
 * Wicket Account Centre
 * WooCommerce
 *
 * @package Wicket
 */

/**
 * WooCommerce Class
 */
class WooCommerce extends WicketAcc
{
	/**
	 * Constructor
	 */
	public function __construct()
	{
		add_action('before_woocommerce_init', [$this, 'HPOS_Compatibility']);
		add_filter('woocommerce_locate_template', [$this, 'override_woocommerce_template'], 10, 3);
		add_action('init', [$this, 'wc_remove_order_again_button']);
		add_action('wicket_header_end', [$this, 'wc_add_acc_banner'], PHP_INT_MAX);
	}

	/**
	 * HPOS compatibility for WooCommerce
	 *
	 * @return void
	 */
	public function HPOS_Compatibility()
	{
		if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
		}
	}

	/**
	 * Override WooCommerce templates
	 *
	 * @param string $template
	 * @param string $template_name
	 * @param string $template_path
	 *
	 * @return string
	 */
	public function override_woocommerce_template($template, $template_name, $template_path)
	{
		if (is_admin()) {
			return $template;
		}

		if ($template_name === 'myaccount/my-account.php') {
			$plugin_template = WICKET_ACC_PLUGIN_TEMPLATE_PATH . 'account-centre/page-wc.php';
			$user_template   = WICKET_ACC_USER_TEMPLATE_PATH . 'account-centre/page-wc.php';

			if (file_exists($user_template)) {
				return $user_template;
			}

			if (file_exists($plugin_template)) {
				return $plugin_template;
			}
		}

		return $template;
	}

	/**
	 * Remove "order again" button from orders and order table inside WooCommerce my account
	 *
	 * @return void
	 */
	public function wc_remove_order_again_button()
	{
		remove_action('woocommerce_order_details_after_order_table', 'woocommerce_order_again_button');
	}

	/**
	 * Adds the global header banner to the WooCommerce my account navigation.
	 *
	 * Will only add the banner if the 'acc_global-headerbanner' option is enabled.
	 *
	 * @return void
	 */
	public function wc_add_acc_banner()
	{
		$acc_banner_enabled = get_field('acc_global-headerbanner', 'option');

		if (!$acc_banner_enabled) {
			return;
		}

		// Only on WooCommerce myaccount pages
		if (!is_account_page()) {
			return;
		}

		$acc_global_headerbanner_page_id = WACC()->get_global_headerbanner_page_id();
		$acc_global_banner_page          = get_post($acc_global_headerbanner_page_id);

		// What happened here?
		if (empty($acc_global_banner_page)) {
			return;
		}

		// Banner content
		$acc_global_banner_content = '<div class="wicket-acc alignfull wp-block-wicket-banner">';
		$acc_global_banner_content .= apply_filters('the_content', $acc_global_banner_page->post_content);
		$acc_global_banner_content .= '</div>';

		echo $acc_global_banner_content;
	}
}
