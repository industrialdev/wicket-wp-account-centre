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

// Single-org skip (WWID-2259). When a site opts in and the current user manages
// exactly one organization, the landing list is one click too many. Redirect
// straight to that organization: members page for membership managers, profile
// page for org editors. Users without an actionable role stay on the landing.
$single_org_skip = (bool) ($orgman_config['presentation']['organization_list']['single_org_skip'] ?? false);
if ($single_org_skip && $roster_mode !== 'groups' && $org_uuid === '') {
    $skip_org_service = new \WicketORM\Services\OrganizationService();
    $skip_user_uuid = wp_get_current_user()->user_login;
    $skip_organizations = $skip_org_service->getUserOrganizations($skip_user_uuid);

    if (is_array($skip_organizations) && !isset($skip_organizations['error'])) {
        $skip_organizations = $skip_org_service->filterActiveOrganizations($skip_organizations, $skip_user_uuid);

        if (is_array($skip_organizations) && count($skip_organizations) === 1) {
            $skip_org = reset($skip_organizations);
            $skip_org_uuid = (string) ($skip_org['id'] ?? '');

            if ($skip_org_uuid !== '') {
                $skip_target_url = '';

                if (\WicketORM\Helpers\PermissionHelper::is_membership_manager($skip_org_uuid)) {
                    $skip_target_url = add_query_arg(
                        'org_uuid',
                        $skip_org_uuid,
                        \WicketORM\Helpers\Helper::getMyAccountPageUrl('organization-members', '/my-account/organization-members/')
                    );
                } elseif (\WicketORM\Helpers\PermissionHelper::can_edit_organization($skip_org_uuid)) {
                    $skip_target_url = add_query_arg(
                        'org_uuid',
                        $skip_org_uuid,
                        \WicketORM\Helpers\Helper::getMyAccountPageUrl('organization-profile', '/my-account/organization-profile/')
                    );
                }

                if ($skip_target_url !== '') {
                    wp_redirect($skip_target_url);
                    exit;
                }
                // No actionable role on the single org: keep the landing page.
            }
        }
    }
}

?>

<div id="org-management-index-app" class="org-management-app wicket-orgman wt_w-full wt_mt-6 wt_mb-6">
    <h2 class="wp-block-heading has-heading-sm-font-size wt_text-2xl wt_font-bold wt_mb-4"><?php echo esc_html($management_title); ?></h2>

    <?php
    if ($roster_mode === 'groups') {
        include dirname(__DIR__) . '/templates-partials/organization-list.php';
    } elseif (!empty($org_uuid)) {
        include dirname(__DIR__) . '/templates-partials/organization-details.php';
    } else {
        include dirname(__DIR__) . '/templates-partials/organization-list.php';
    }
?>
</div>
