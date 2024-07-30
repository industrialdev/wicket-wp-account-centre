<?php

/**
 * My Account Dashboard
 *
 * Shows the first intro screen on the account dashboard.
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/myaccount/dashboard.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see     https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 4.4.0
 */

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

?>

<?php

$wicket_acc_custom_dashboard_id = get_option('wicket_acc_set_ep_custom_dashboard');

// if WPML is installed, get the translated AC landing page if it exists
if (defined('ICL_SITEPRESS_VERSION')) {
	$type = apply_filters('wpml_element_type', get_post_type($wicket_acc_custom_dashboard_id));
	$trid = apply_filters('wpml_element_trid', false, 	$wicket_acc_custom_dashboard_id, $type);
	$translations = apply_filters('wpml_get_element_translations', array(), $trid, $type);
	if (isset($translations[ICL_LANGUAGE_CODE])) {
		$wicket_acc_custom_dashboard_id = $translations[ICL_LANGUAGE_CODE]->element_id;
	}
}

if ($wicket_acc_custom_dashboard_id) {
	$content_post = get_post($wicket_acc_custom_dashboard_id);
	$content = $content_post->post_content;
	$content = apply_filters('the_content', $content);
	$content = str_replace(']]>', ']]&gt;', $content);
	echo $content;
}

?>

<?php
/**
 * My Account dashboard.
 *
 * @since 2.6.0
 */
do_action('woocommerce_account_dashboard');

/**
 * Deprecated woocommerce_before_my_account action.
 *
 * @deprecated 2.6.0
 */
do_action('woocommerce_before_my_account');

/**
 * Deprecated woocommerce_after_my_account action.
 *
 * @deprecated 2.6.0
 */
do_action('woocommerce_after_my_account');

/* Omit closing PHP tag at the end of PHP files to avoid "headers already sent" issues. */
