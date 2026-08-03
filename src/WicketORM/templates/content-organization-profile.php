<?php

/**
 * Content-only template for Organization Management Index.
 * This template contains only the OrgMan content to be injected after the_content.
 */

namespace WicketORM\Templates;

if (!is_user_logged_in()) {
    wp_redirect(wp_login_url());
    exit;
}

// Roster mode selection
$configService = new \WicketORM\Services\ConfigService();
$orgman_config = \WicketORM\Services\ConfigService::getConfig();
$roster_mode = $configService->getRosterMode();
$default_organization_title = __('Manage Organizations', 'wicket-acc');
$use_custom_organization_title = !empty($orgman_config['ui']['organization_list']['use_custom_title']);
$custom_organization_title = isset($orgman_config['ui']['organization_list']['custom_title'])
    ? trim((string) $orgman_config['ui']['organization_list']['custom_title'])
    : '';
$organization_title = ($use_custom_organization_title && $custom_organization_title !== '')
    ? $custom_organization_title
    : $default_organization_title;
$management_title = $roster_mode === 'groups' ? __('Manage Groups', 'wicket-acc') : $organization_title;

// Normalize query param: prefer org_uuid; redirect from org_id => org_uuid
$org_uuid = isset($_GET['org_uuid']) ? sanitize_text_field($_GET['org_uuid']) : '';
$org_id_fallback = isset($_GET['org_id']) ? sanitize_text_field($_GET['org_id']) : '';
if (empty($org_uuid) && !empty($org_id_fallback)) {
    $current_url = home_url(add_query_arg([]));
    // Preserve existing params but replace org_id with org_uuid
    $params = $_GET;
    unset($params['org_id']);
    $params['org_uuid'] = $org_id_fallback;
    wp_redirect(add_query_arg(array_map('sanitize_text_field', $params), $current_url));
    exit;
}

$org_type = '';
if ($roster_mode !== 'groups' && !empty($org_uuid) && function_exists('wicket_get_organization')) {
    $org_response = wicket_get_organization($org_uuid);
    if (is_array($org_response) && isset($org_response['data']['attributes']['type'])) {
        $org_type = $org_response['data']['attributes']['type'];
    }
}

$status = isset($_REQUEST['status']) ? sanitize_text_field(wp_unslash($_REQUEST['status'])) : '';

?>

<div id="org-management-index-app"
    class="org-management-app wicket-orgman wt_w-full wt_mt-6 wt_mb-6"
    data-org-type="<?php echo esc_attr($org_type); ?>">
    <?php
    // Back to Manage Groups / Organizations landing when the user has drilled into a specific context.
    $back_to_landing_show = $roster_mode === 'groups' || $org_uuid !== '';
if ($back_to_landing_show) :
    $back_to_landing_label = $roster_mode === 'groups'
        ? __('Back to Manage Groups', 'wicket-acc')
        : __('Back to Manage Organizations', 'wicket-acc');
    $back_to_landing_url = \WicketORM\Helpers\Helper::getMyAccountPageUrl(
        'organization-management',
        '/my-account/organization-management/'
    );
    ?>
        <a href="<?php echo esc_url($back_to_landing_url); ?>"
            class="wt_inline-flex wt_items-center wt_gap-1 wt_text-primary-600 wt_hover_text-primary-700 wt_text-sm wt_mb-4 underline underline-offset-4">
            <span aria-hidden="true">&larr;</span>
            <?php echo esc_html($back_to_landing_label); ?>
        </a>
    <?php endif; ?>

    <h1 class="wt_text-2xl wt_font-bold wt_mb-4"><?php echo esc_html($management_title); ?></h1>

    <?php if ($status === 'success') : ?>
        <div class="alert alert-success wt_my-3 wt_p-3" role="alert">
            <?php esc_html_e('Organization updated successfully!', 'wicket-acc'); ?>
        </div>
    <?php endif; ?>

    <?php
    if ($roster_mode === 'groups') {
        $group_uuid = isset($_GET['group_uuid']) ? sanitize_text_field((string) $_GET['group_uuid']) : '';
        if (empty($org_uuid) && !empty($group_uuid) && function_exists('wicket_get_group')) {
            $group_response = wicket_get_group($group_uuid);
            if (is_array($group_response)) {
                $org_uuid = $group_response['data']['relationships']['organization']['data']['id'] ?? $org_uuid;
            }
        }
    }
?>

    <?php if (!empty($org_uuid)) : ?>
        <div class="org-management-profile-wrap" id="organization-summary">
            <?php include dirname(__DIR__) . '/templates-partials/organization-details.php'; ?>
        </div>

        <div class="org-management-profile-content wt_mt-6">
            <?php include dirname(__DIR__) . '/templates-partials/organization-profile.php'; ?>
        </div>
    <?php else : ?>
        <div class="org-management-profile-wrap" id="organization-list">
            <?php include dirname(__DIR__) . '/templates-partials/organization-list.php'; ?>
        </div>
    <?php endif; ?>

</div>
