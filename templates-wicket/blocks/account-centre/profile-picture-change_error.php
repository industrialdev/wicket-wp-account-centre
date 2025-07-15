<?php

namespace WicketAcc;

// No direct access
defined('ABSPATH') || exit;

/*
 * Available $args[] variables:
 *
 * error_message - Specific error message
 * error_type - Type of error (for styling/debugging)
 * pp_max_size - Maximum file size in MB
 * pp_extensions - Array of allowed file extensions
 */
?>
<section class="container wicket-acc-profile-picture wicket-acc-profile-picture__error <?php echo defined('WICKET_WP_THEME_V2') ? 'wicket-acc-profile-picture--v2' : '' ?>">
    <h3><?php esc_html_e('Error', 'wicket-acc'); ?></h3>

    <?php if (!empty($args['error_message'])): ?>
        <p><strong><?php echo esc_html($args['error_message']); ?></strong></p>
    <?php else: ?>
        <p><?php esc_html_e('There was an error while trying to upload the profile picture. Please try again.', 'wicket-acc'); ?></p>
    <?php endif; ?>

    <?php if (!empty($args['pp_max_size']) && !empty($args['pp_extensions'])): ?>
        <div class="upload-requirements">
            <h4><?php esc_html_e('Upload Requirements:', 'wicket-acc'); ?></h4>
            <ul>
                <li><?php printf(esc_html__('Maximum file size: %dMB', 'wicket-acc'), $args['pp_max_size']); ?></li>
                <li><?php printf(esc_html__('Allowed formats: %s', 'wicket-acc'), implode(', ', array_map('strtoupper', $args['pp_extensions']))); ?></li>
                <li><?php esc_html_e('File must be a valid image', 'wicket-acc'); ?></li>
            </ul>
        </div>
    <?php endif; ?>
</section>
