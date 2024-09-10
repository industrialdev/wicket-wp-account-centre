<?php
// No direct access
defined('ABSPATH') || exit;

/**
 * Archive My Account
 *
 * @package Wicket
 */

// We will just redirect user to my-account dashboard
$acc_dashboard_id = get_field('acc_page_dashboard', 'option');

if ($acc_dashboard_id) {
	wp_safe_redirect(get_permalink($acc_dashboard_id));
	exit;
} else {
	// Or to the home
	wp_safe_redirect(home_url());
	exit;
}
