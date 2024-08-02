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
	}

	/**
	 * HOPS compatibility for WooCommerce
	 *
	 * @return void
	 */
	public function HPOS_Compatibility()
	{
		if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
		}
	}
}
