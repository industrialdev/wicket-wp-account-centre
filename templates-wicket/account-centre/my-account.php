<?php

/**
 * My Account page
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/myaccount/my-account.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see     https://woo.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 3.5.0
 */

defined('ABSPATH') || exit;

/**
 * My Account navigation.
 *
 * @since 2.6.0
 */

$acc_sidebar_location = get_field('acc_sidebar_location', 'option');

if (empty($acc_sidebar_location)) {
	$acc_sidebar_location = 'right';
}
?>

<div class="woocommerce-wicket--container wicket-acc-container">
	<?php
	if ('left' === $acc_sidebar_location) {
		do_action('woocommerce_account_navigation');
	}
	?>

	<div class="woocommerce-wicket--account-centre">
		<?php
		/**
		 * My Account content.
		 *
		 * @since 2.6.0
		 */
		do_action('woocommerce_account_content');
		?>
	</div>

	<?php
	if ('right' === $acc_sidebar_location) {
		do_action('woocommerce_account_navigation');
	}
	?>
</div>
