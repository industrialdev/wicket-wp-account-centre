<?php

/**
 * Content template for Supplemental Members page.
 */

declare(strict_types=1);

namespace WicketORM\Templates;

// Ensure this file is not accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

// Get URL parameters
$org_uuid = isset($_GET['org_uuid']) ? sanitize_text_field($_GET['org_uuid']) : '';
$membership_id = isset($_GET['membership_id']) ? sanitize_text_field($_GET['membership_id']) : '';
if (empty($membership_id) && isset($_GET['membership_uuid'])) {
    $membership_id = sanitize_text_field($_GET['membership_uuid']);
}
$gf_id = isset($_GET['gf_id']) ? absint($_GET['gf_id']) : 0;

// Helper function to get my-account CPT page URL (WPML-aware)
if (!function_exists('WicketORM\Templates\get_my_account_page_url')) {
    function get_my_account_page_url($slug)
    {
        return \WicketORM\Helpers\Helper::getMyAccountPageUrl($slug, "/my-account/{$slug}/");
    }
}

// Check if user can purchase additional seats
$configService = new \WicketORM\Services\ConfigService();
$additional_seats_service = new \WicketORM\Services\AdditionalSeatsService($configService);

if (!$additional_seats_service->canPurchaseAdditionalSeats($org_uuid)) {
    ?>
<div class="woocommerce">
    <div class="woocommerce-notices-wrapper">
        <div class="woocommerce-error">
            <?php esc_html_e('You are not authorized to purchase additional seats for this organization.', 'wicket-acc'); ?>
        </div>
    </div>
    <p>
        <a href="<?php echo esc_url(get_my_account_page_url('organization-members')); ?>"
            class="button">
            <?php esc_html_e('Back to Organization Members', 'wicket-acc'); ?>
        </a>
    </p>
</div>
<?php
    return;
}

// Get organization information
$organizationService = new \WicketORM\Services\OrganizationService();
$organizations = $organizationService->getUserOrganizations(wp_get_current_user()->user_login);
$current_organization = null;
$form_id = $gf_id > 0 ? $gf_id : 59;
$number_input_id = 'input_' . $form_id . '_3';
$submit_button_id = 'gform_submit_button_' . $form_id;

foreach ($organizations as $org) {
    if ($org['id'] === $org_uuid) {
        $current_organization = $org;
        break;
    }
}

?>
<div id="orgman-supplemental-members-app"
    class="woocommerce wicket-orgman-supplemental"
    data-number-input-id="<?php echo esc_attr($number_input_id); ?>"
    data-submit-button-id="<?php echo esc_attr($submit_button_id); ?>"
    data-msg-valid-number="<?php echo esc_attr(__('Please enter a valid number for additional seats.', 'wicket-acc')); ?>"
    data-msg-no-negative="<?php echo esc_attr(__('Number of additional seats cannot be negative.', 'wicket-acc')); ?>"
    data-msg-no-zero="<?php echo esc_attr(__('You cannot purchase zero seats. Please enter a number greater than 0 to proceed with your purchase.', 'wicket-acc')); ?>"
    data-msg-whole-number="<?php echo esc_attr(__('Please enter a whole number for additional seats.', 'wicket-acc')); ?>">
    <div class="entry-content">
        <h1><?php esc_html_e('Purchase Additional Seats', 'wicket-acc'); ?>
        </h1>

        <?php if ($current_organization): ?>
        <div class="orgman-org-info">
            <h3><?php esc_html_e('Organization Details', 'wicket-acc'); ?>
            </h3>
            <p><strong><?php esc_html_e('Organization:', 'wicket-acc'); ?></strong>
                <?php echo esc_html($current_organization['name'] ?? ''); ?>
            </p>
            <p><strong><?php esc_html_e('Membership ID:', 'wicket-acc'); ?></strong>
                <?php echo esc_html($membership_id); ?>
            </p>
        </div>
        <?php endif; ?>
    </div>
</div>
