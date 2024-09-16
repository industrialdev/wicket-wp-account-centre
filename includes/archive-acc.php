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
	$redirect_url = get_permalink($acc_dashboard_id);
} else {
	// Or to the home
	$redirect_url = home_url();
}

/**
 * Some websites have improper HTML output before the get_header() call (bad plugins, old theme, etc.), so we need to redirect them without a PHP's header redirect.
 */
if (!headers_sent()) {
	wp_safe_redirect($redirect_url);
} else {
	// Sorry. Is there any other way to do this?
	echo '<meta http-equiv="refresh" content="0; url=' . esc_url($redirect_url) . '" />';
	echo '<script type="text/javascript">window.location.href="' . esc_url($redirect_url) . '";</script>';
}
die();
