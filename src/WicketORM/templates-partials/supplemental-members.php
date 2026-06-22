<?php

/**
 * Template for Additional Seats Purchase form.
 */

declare(strict_types=1);

namespace WicketORM\Templates;

// Ensure this file is not accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Renders the supplemental members form for purchasing additional seats.
 */

// Get URL parameters for form rendering
$org_uuid = isset($_GET['org_uuid']) ? sanitize_text_field($_GET['org_uuid']) : '';
$membership_id = isset($_GET['membership_id']) ? sanitize_text_field($_GET['membership_id']) : '';
if (empty($membership_id) && isset($_GET['membership_uuid'])) {
    $membership_id = sanitize_text_field($_GET['membership_uuid']);
}

// Helper function for back link (WPML-aware)
if (!function_exists('WicketORM\Templates\get_my_account_page_url')) {
    function get_my_account_page_url($slug)
    {
        return \WicketORM\Helpers\Helper::getMyAccountPageUrl($slug, "/my-account/{$slug}/");
    }
}

?>
<div class="woocommerce">
    <div class="orgman-form-container">
        <div class="woocommerce-info">
            <p><?php esc_html_e('Please complete the form below to purchase additional seats for your organization membership.', 'wicket-acc'); ?>
            </p>
            <p><?php esc_html_e('After purchase, the additional seats will be automatically added to your subscription.', 'wicket-acc'); ?>
            </p>
        </div>

        <?php
            // Render Gravity Forms form
            if (class_exists('GFForms')) {
                echo \WicketORM\Helpers\GravityFormsHelper::get_form_html($org_uuid, $membership_id);
            } else {
                ?>
        <div class="woocommerce-error">
            <?php esc_html_e('Gravity Forms is not available. Please contact support.', 'wicket-acc'); ?>
        </div>
        <?php
            }
?>
    </div>

    <!--<div class="orgman-back-link">
            <a href="<?php echo esc_url(get_my_account_page_url('organization-members')); ?>"
    class="button">
    <?php esc_html_e('Back to Organization Members', 'wicket-acc'); ?>
    </a>
</div>-->
</div>
</div>
