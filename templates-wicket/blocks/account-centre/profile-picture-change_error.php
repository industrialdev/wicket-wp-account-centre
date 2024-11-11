<?php

namespace WicketAcc;

// No direct access
defined('ABSPATH') || exit;

/**
 * Available $args[] variables:
 *
 * NONE
 */
?>
<section class="container wicket-acc-profile-picture wicket-acc-profile-picture__error <?php echo defined('WICKET_WP_THEME_V2') ? 'wicket-acc-profile-picture--v2' : '' ?>">
	<h3><?php esc_html_e('Error', 'wicket-acc'); ?></h3>
	<p><?php esc_html_e('There was an error while trying to upload the profile picture. Please try again.', 'wicket-acc'); ?></p>
</section>
