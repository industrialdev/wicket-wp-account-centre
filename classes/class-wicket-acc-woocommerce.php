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
		// Only add the action if WooCommerce is active
		if ($this->is_woocommerce_active()) {
			add_action('before_woocommerce_init', [$this, 'HPOS_Compatibility']);
		}
	}

	/**
	 * Check if WooCommerce is active
	 *
	 * @return bool
	 */
	private function is_woocommerce_active()
	{
		return class_exists('WooCommerce');
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
}
