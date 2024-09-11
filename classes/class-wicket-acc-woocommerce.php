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
		// HPOS compatibility for WooCommerce
		add_action('before_woocommerce_init', [$this, 'HPOS_Compatibility']);
		// Filter URLs to catch WooCommerce endpoints
		add_action('template_redirect', [$this, 'catch_wc_endpoint_request']);
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
	 * Get all current WC endpoints
	 *
	 * @return array
	 */
	private function get_woocommerce_endpoints()
	{
		$query_vars = WC()->query->get_query_vars();
		$endpoints  = [];

		foreach ($query_vars as $key => $value) {
			// Endpoints are typically strings, not empty
			if (is_string($value) && !empty($value)) {
				$endpoints[$key] = $value;
			}
		}

		return $endpoints;
	}

	/**
	 * Filter requests and check if we are inside a WooCommerce endpoint
	 *
	 * @return bool
	 */
	public function catch_wc_endpoint_request()
	{
		global $wp;

		// Get all current WC endpoints
		$endpoints = $this->get_woocommerce_endpoints();

		if (empty($endpoints) || !is_array($endpoints)) {
			return false;
		}

		// If the URL is not the base WC myaccount page, we're not in a WC endpoint
		$wc_myaccount_page_id = wc_get_page_id('myaccount');

		// Get wc_myaccount_page_id page slug
		$wc_myaccount_page_slug = get_page_uri($wc_myaccount_page_id);

		// Does the current URL match the wc_myaccount_page_slug?
		if (!str_contains($wp->request, $wc_myaccount_page_slug)) {
			return false;
		}

		// Catch the endpoint name
		$endpoint = WC()->query->get_current_endpoint();

		// Check if we have a page to catch this endpoint
		$maybe_page_id = get_field('acc_page_' . $endpoint, 'option');

		if ($maybe_page_id) {
			// Get this page URL, and pass everything from the WC endpoint to it
			$acc_page_url = get_permalink($maybe_page_id);

			// Add the rest of the requested URL to the page URL. Remove everything from endpoint to the beginning of the URL. Included the endpoint itself.
			$end_position = strpos($wp->request, $endpoint);

			if ($end_position !== false) {
				$query_string = substr($wp->request, $end_position + strlen($endpoint));

				// Remove leading slash if present
				$query_string = ltrim($query_string, '/');

				// Add the query string to the page URL
				if (!empty($query_string)) {
					$acc_page_url = trailingslashit(trailingslashit($acc_page_url) . $query_string);
				}
			}

			// Redirect to the page
			wp_safe_redirect($acc_page_url);
			exit;
		}

		return false;
	}
}
