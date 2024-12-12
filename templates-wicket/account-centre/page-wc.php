<?php

/**
 * WooCommerce My Account page.
 */
defined('ABSPATH') || exit;

$acc_sidebar_location = get_field('acc_sidebar_location', 'option');

if (empty($acc_sidebar_location)) {
    $acc_sidebar_location = 'right';
}
?>

<div class="woocommerce woocommerce-wicket--container wicket-acc wicket-acc-page wicket-acc-woocommerce wicket-acc-container">
    <?php
    if ('left' === $acc_sidebar_location) {
        WACC()->renderAccSidebar();
    }
?>

    <div class="woocommerce-wicket--account-centre">
        <?php
    do_action('woocommerce_account_content');
?>
    </div>

    <?php
    if ('right' === $acc_sidebar_location) {
        WACC()->renderAccSidebar();
    }
?>
</div>
