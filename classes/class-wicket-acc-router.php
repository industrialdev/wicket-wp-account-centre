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
	private array $acc_pages_map = [
		'account-centre'                 => 'Account Centre',
		'edit-profile'                   => 'Edit Profile',
		'events'                         => 'My Events',
		'events-past'                    => 'Past Events',
		'jobs'                           => 'My Jobs',
		'job-view'                       => 'Job View',
		'job-post'                       => 'Post a Job',
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
		add_action('admin_init', [$this, 'init_all_pages']);
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
				"SELECT ID FROM $wpdb->posts WHERE post_name = %s AND post_type = 'wicket_acc' AND post_status = 'publish'",
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
}
