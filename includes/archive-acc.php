<?php

// No direct access
defined('ABSPATH') || exit;

/**
 * Archive My Account.
 */

// We will just redirect user to my-account dashboard
$redirect_url = WACC()->get_account_page_url('dashboard');

/*
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
