<?php

namespace WicketAcc;

// No direct access
defined('ABSPATH') || exit;

/**
 * Router Class
 * Implements hexbit/router library
 */
class Router extends WicketAcc
{
	private $acc_page_slug = 'account-centre';
	private $acc_page_name = 'Account Centre';
	private $acc_page_id   = '';
	private $acc_pages_map = [
		'account-centre'                 => 'Account Centre',
		'edit-profile'                   => 'Edit Profile',
		'events'                         => 'My Events',
		'event-single'                   => 'Event',
		'events-past'                    => 'Past Events',
		'jobs'                           => 'My Jobs',
		'job-single'                     => 'Job',
		'job-post'                       => 'Post a Jobs',
		'payments-settings'              => 'Payments Settings',
		'locations-banners-subsidiaries' => 'View Locations, Banners and Subsidiaries',
		'membership-history'             => 'Membership History',
		'purchase-history'               => 'Purchase History',
		'change-password'                => 'Change Password',
	];

	/**
	 * Constructor
	 */
	public function __construct()
	{
		add_action('admin_init', [$this, 'acc_init_all_pages']);
	}

	/**
	 * Get ACC root page ID
	 *
	 * @return int
	 */
	public function get_acc_root_page_id()
	{
		$acc_page_id = get_field('acc_page_account-centre', 'option');

		return $acc_page_id;
	}

	/**
	 * Get ACC Page Slug
	 *
	 * @return string
	 */
	public function get_acc_page_slug()
	{
		$acc_page_id   = $this->get_acc_root_page_id();
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
		$acc_page_id  = $this->get_acc_root_page_id();
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
	public function acc_create_page($slug, $name)
	{
		if ($this->acc_page_exists($slug)) {
			return false;
		}

		// Create requested page as a child of ACC index page
		$page_id = wp_insert_post(
			[
				'post_type'    => 'wicket_acc',
				'post_title'   => $name,
				'post_name'    => $slug,
				'post_status'  => 'publish',
				'comment_status' => 'closed',
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
	 * Check if page exists, by slug
	 *
	 * @param string $slug
	 *
	 * @return bool
	 */
	public function acc_page_exists($slug)
	{
		$page_id = get_field('acc_page_' . $slug, 'option');

		if ($page_id) {
			return true;
		}

		return false;
	}

	/**
	 * Create all ACC pages
	 * Run only once
	 *
	 * @return void
	 */
	public function acc_init_all_pages()
	{
		// Create all other pages
		foreach ($this->acc_pages_map as $slug => $name) {
			$this->acc_create_page($slug, $name);
		}
	}
}
