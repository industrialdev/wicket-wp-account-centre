<?php

/**
 * Content-only template for Organization Contacts (ESCRCS relationship-based roster).
 * This template contains only the OrgMan contacts content to be injected after the_content.
 */

namespace WicketORM\Templates;

if (!is_user_logged_in()) {
    wp_redirect(wp_login_url());
    exit;
}

$orgman_config = \WicketORM\Services\ConfigService::getConfig();
$contacts_config = $orgman_config['contacts'] ?? [];

// Feature toggle guard
if (empty($contacts_config['enabled'])) {
    return;
}

$org_uuid = isset($_GET['org_uuid']) ? sanitize_text_field($_GET['org_uuid']) : '';
if (empty($org_uuid)) {
    $org_id_fallback = isset($_GET['org_id']) ? sanitize_text_field($_GET['org_id']) : '';
    if (!empty($org_id_fallback)) {
        $org_uuid = $org_id_fallback;
    }
}

if (empty($org_uuid)) {
    $redirect_url = \WicketORM\Helpers\Helper::getMyAccountPageUrl('organization-management', '/my-account/organization-management/');
    wp_redirect(esc_url_raw($redirect_url));
    exit;
}

// Permission guard: only membership_manager can view contacts
if (!\WicketORM\Helpers\PermissionHelper::can_view_contacts($org_uuid)) {
    echo '<p class="wt_text-gray-500 wt_p-4">' . esc_html__('You do not have permission to view contacts for this organization.', 'wicket-acc') . '</p>';

    return;
}

// Organization name for header
$org_name = '';
if (function_exists('wicket_get_organization')) {
    $org_response = wicket_get_organization($org_uuid);
    if (is_array($org_response) && isset($org_response['data']['attributes']['name'])) {
        $org_name = $org_response['data']['attributes']['name'];
    }
}

// Back URL
$back_url = \WicketORM\Helpers\Helper::getMyAccountPageUrl('organization-management', '/my-account/organization-management/');
if (!empty($org_uuid)) {
    $back_url = add_query_arg('org_uuid', $org_uuid, $back_url);
}

?>
<div id="org-management-contacts-app" class="org-management-app wicket-orgman wt_w-full wt_mt-6 wt_mb-6">
    <h2 class="wp-block-heading has-heading-sm-font-size wt_text-2xl wt_font-bold wt_mb-4">
        <?php esc_html_e('Manage my Organizations', 'wicket-acc'); ?>
    </h2>

    <a href="<?php echo esc_url($back_url); ?>" class="wt_inline-flex wt_items-center wt_text-primary-600 wt_hover_underline wt_mb-4">
        &larr; <?php esc_html_e('Back to My Organization', 'wicket-acc'); ?>
    </a>

    <?php if ($org_name !== ''): ?>
        <div class="wt_bg-light-neutral wt_rounded-card wt_p-4 wt_mb-6 wt_border wt_border-color">
            <h3 class="wt_text-xl wt_font-semibold wt_text-content"><?php echo esc_html($org_name); ?></h3>
        </div>
    <?php endif; ?>

    <div class="org-management-contacts-content">
        <?php include dirname(__DIR__) . '/templates-partials/organization-contacts.php'; ?>
    </div>
</div>
