<?php

namespace WicketAcc;

// No direct access
defined('ABSPATH') || exit;

/**
 * AccRouter Class
 * Implements hexbit/router library
 */
class AccRouter extends WicketAcc
{
	/**
	 * Constructor
	 */
	public function __construct()
	{
	}

	/**
	 * Get ACC Page ID
	 *
	 * @return int
	 */
	public function get_acc_page_id()
	{
		$acc_page_id = get_field('account_centre_index', 'option');

		return $acc_page_id;
	}

	/**
	 * Get ACC Page Slug
	 *
	 * @return string
	 */
	public function get_acc_page_slug()
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
	public function get_acc_page_url()
	{
		$acc_page_id   = $this->get_acc_page_id();
		$acc_page_url  = get_permalink($acc_page_id);

		return $acc_page_url;
	}
}
