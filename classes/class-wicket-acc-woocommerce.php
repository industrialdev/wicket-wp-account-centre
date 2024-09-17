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
		if(is_admin()) {
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
}
