<?php

namespace WicketAcc;

// No direct access
defined('ABSPATH') || exit;

/*
 * Available $args[] variables:
 *
 * NONE
 */
?>
<section class="container wicket-acc-profile-picture wicket-acc-profile-picture__success <?php echo defined('WICKET_WP_THEME_V2') ? 'wicket-acc-profile-picture--v2' : '' ?>">
    <h3><?php esc_html_e('Success', 'wicket-acc'); ?></h3>
    <p><?php esc_html_e('Your profile picture has been updated successfully.', 'wicket-acc'); ?></p>
</section>
