<?php

/**
 * Content-only template for Organization Members Bulk Upload.
 * This template is injected after page content on my-account slug: organization-members-bulk.
 */

namespace WicketORM\Templates;

if (!is_user_logged_in()) {
    wp_redirect(wp_login_url());
    exit;
}

$configService = new \WicketORM\Services\ConfigService();
$roster_mode = (string) $configService->getRosterMode();

$org_uuid = isset($_GET['org_uuid']) ? sanitize_text_field((string) $_GET['org_uuid']) : '';
$group_uuid = isset($_GET['group_uuid']) ? sanitize_text_field((string) $_GET['group_uuid']) : '';
$org_id_fallback = isset($_GET['org_id']) ? sanitize_text_field((string) $_GET['org_id']) : '';

if (empty($org_uuid) && !empty($org_id_fallback)) {
    $current_url = home_url(add_query_arg([]));
    $params = $_GET;
    unset($params['org_id']);
    $params['org_uuid'] = $org_id_fallback;
    wp_redirect(add_query_arg(array_map('sanitize_text_field', $params), $current_url));
    exit;
}

$member_list_config = \WicketORM\Services\ConfigService::getConfig()['ui']['member_list'] ?? [];
$show_bulk_upload = (bool) ($member_list_config['show_bulk_upload'] ?? false);

$membershipService = new \WicketORM\Services\MembershipService();
$user_uuid = (string) wp_get_current_user()->user_login;
$bulk_page_url = \WicketORM\Helpers\Helper::getMyAccountPageUrl(
    'organization-members-bulk',
    '/my-account/organization-members-bulk/'
);

$build_base_query_args = static function (): array {
    $args = [];
    foreach ($_GET as $key => $value) {
        if (!is_scalar($value)) {
            continue;
        }
        if ($key === 'org_id' || $key === 'org_uuid' || $key === 'group_uuid') {
            continue;
        }
        $args[$key] = sanitize_text_field(wp_unslash((string) $value));
    }

    return $args;
};

$membership_uuid = '';

if ($roster_mode === 'groups') {
    $group_service = new \WicketORM\Services\GroupService();
    $manageable_groups = [];
    $seen_group_ids = [];
    $page = 1;
    $total_pages = 1;

    do {
        $groups_response = $group_service->getManageableGroups($user_uuid, [
            'page' => $page,
            'size' => $group_service->getGroupListPageSize(),
            'query' => '',
        ]);

        $groups_data = is_array($groups_response['data'] ?? null) ? $groups_response['data'] : [];
        foreach ($groups_data as $group_item) {
            $group = is_array($group_item['group'] ?? null) ? $group_item['group'] : [];
            $group_id = (string) ($group['id'] ?? '');
            if ($group_id === '' || isset($seen_group_ids[$group_id])) {
                continue;
            }
            $seen_group_ids[$group_id] = true;

            $group_attrs = is_array($group['attributes'] ?? null) ? $group['attributes'] : [];
            $group_name = (string) ($group_attrs['name'] ?? $group_attrs['name_en'] ?? $group_attrs['name_fr'] ?? '');
            if ($group_name === '') {
                $group_name = __('Unknown Group', 'wicket-acc');
            }

            $manageable_groups[] = [
                'group_uuid' => $group_id,
                'group_name' => $group_name,
                'org_uuid' => (string) ($group_item['org_uuid'] ?? ''),
                'org_name' => (string) ($group_item['org_name'] ?? ''),
                'role_slug' => (string) ($group_item['role_slug'] ?? ''),
            ];
        }

        $meta_page = is_array($groups_response['meta']['page'] ?? null) ? $groups_response['meta']['page'] : [];
        $total_pages = max(1, (int) ($meta_page['total_pages'] ?? 1));
        $page++;
    } while ($page <= $total_pages);

    $group_access = [
        'allowed' => false,
        'org_uuid' => '',
        'org_identifier' => '',
        'role_slug' => '',
    ];

    if ($show_bulk_upload && $group_uuid === '' && count($manageable_groups) === 1) {
        $redirect_args = $build_base_query_args();
        $redirect_args['group_uuid'] = (string) $manageable_groups[0]['group_uuid'];
        if ((string) $manageable_groups[0]['org_uuid'] !== '') {
            $redirect_args['org_uuid'] = (string) $manageable_groups[0]['org_uuid'];
        }
        wp_redirect(add_query_arg($redirect_args, $bulk_page_url));
        exit;
    }

    if ($group_uuid !== '') {
        $group_access = $group_service->canManageGroup($group_uuid, $user_uuid);
        if (!empty($group_access['allowed'])) {
            $resolved_org_uuid = (string) ($group_access['org_uuid'] ?? '');
            if ($resolved_org_uuid !== '') {
                $org_uuid = $resolved_org_uuid;
            }
        }
    }

    if ($org_uuid !== '') {
        $membership_uuid = (string) $membershipService->getMembershipForOrganization($org_uuid);
    }
    ?>
    <div id="org-management-members-bulk-app"
        class="org-management-app wicket-orgman wt_w-full wt_mt-6 wt_mb-6"
        data-signals='{"membersLoading": false, "bulkUploadSubmitting": false}'>
        <h1 class="wt_text-2xl wt_font-bold wt_mb-4"><?php esc_html_e('Bulk Upload Members', 'wicket-acc'); ?></h1>

        <?php if (!$show_bulk_upload) : ?>
            <div class="wt_bg-yellow-100 wt_border wt_border-yellow-400 wt_text-yellow-800 wt_px-4 wt_py-3 wt_rounded-sm wt_mb-4">
                <?php esc_html_e('Bulk upload is currently disabled by configuration.', 'wicket-acc'); ?>
            </div>
        <?php elseif ($group_uuid === '' && count($manageable_groups) > 1) : ?>
            <p class="wt_text-sm wt_text-content wt_mb-4">
                <?php esc_html_e('Select a group to continue with bulk member upload.', 'wicket-acc'); ?>
            </p>
            <div class="wt_w-full wt_flex wt_flex-col wt_gap-3" role="list">
                <?php foreach ($manageable_groups as $group_item) :
                    $item_query_args = $build_base_query_args();
                    $item_query_args['group_uuid'] = (string) $group_item['group_uuid'];
                    if ((string) $group_item['org_uuid'] !== '') {
                        $item_query_args['org_uuid'] = (string) $group_item['org_uuid'];
                    }
                    ?>
                    <a href="<?php echo esc_url(add_query_arg($item_query_args, $bulk_page_url)); ?>"
                        class="button button--secondary component-button wt_inline-flex wt_w-full wt_items-center wt_justify-between wt_p-4"
                        role="listitem">
                        <span>
                            <?php echo esc_html((string) $group_item['group_name']); ?>
                            <?php if ((string) $group_item['org_name'] !== '') : ?>
                                <span class="wt_text-xs wt_text-content"><?php echo esc_html(' - ' . (string) $group_item['org_name']); ?></span>
                            <?php endif; ?>
                        </span>
                        <span aria-hidden="true">→</span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php elseif ($group_uuid === '') : ?>
            <div class="wt_bg-red-100 wt_border wt_border-red-400 wt_text-red-700 wt_px-4 wt_py-3 wt_rounded-sm wt_mb-4">
                <?php esc_html_e('You do not have access to bulk upload members for any groups.', 'wicket-acc'); ?>
            </div>
        <?php elseif (empty($group_access['allowed'])) : ?>
            <div class="wt_bg-red-100 wt_border wt_border-red-400 wt_text-red-700 wt_px-4 wt_py-3 wt_rounded-sm wt_mb-4">
                <?php esc_html_e('You do not have permission to bulk add members for this group.', 'wicket-acc'); ?>
            </div>
        <?php else : ?>
            <?php
            $bulk_upload_endpoint = \WicketORM\Helpers\template_url() . 'process/bulk-upload-members';
            $bulk_upload_messages_id = 'bulk-upload-messages-' . sanitize_html_class($org_uuid ?: $group_uuid ?: 'default');
            include dirname(__DIR__) . '/templates-partials/members-bulk-upload.php';
            ?>
        <?php endif; ?>
    </div>
    <?php

    return;
}

$organizationService = new \WicketORM\Services\OrganizationService();
$organizations = $organizationService->getUserOrganizations($user_uuid);
if (!is_array($organizations)) {
    $organizations = [];
}
$organizations = $organizationService->filterActiveOrganizations($organizations, $user_uuid);

$manageable_organizations = [];
foreach ($organizations as $organization) {
    $candidate_org_uuid = (string) ($organization['id'] ?? '');
    if ($candidate_org_uuid === '') {
        continue;
    }

    if (!\WicketORM\Helpers\PermissionHelper::can_add_members($candidate_org_uuid)) {
        continue;
    }

    $org_name = (string) ($organization['org_name'] ?? $organization['name'] ?? '');
    if ($org_name === '') {
        $org_name = __('Unknown Organization', 'wicket-acc');
    }

    $manageable_organizations[] = [
        'id' => $candidate_org_uuid,
        'name' => $org_name,
    ];
}

$manageable_org_ids = array_values(array_map(static function ($org): string {
    return (string) ($org['id'] ?? '');
}, $manageable_organizations));

if ($show_bulk_upload && empty($org_uuid) && count($manageable_organizations) === 1) {
    $redirect_query_args = $build_base_query_args();
    $redirect_query_args['org_uuid'] = $manageable_organizations[0]['id'];
    wp_redirect(add_query_arg($redirect_query_args, $bulk_page_url));
    exit;
}

if ($org_uuid !== '') {
    $membership_uuid = (string) $membershipService->getMembershipForOrganization($org_uuid);
}
?>
<div id="org-management-members-bulk-app"
    class="org-management-app wicket-orgman wt_w-full wt_mt-6 wt_mb-6"
    data-signals='{"membersLoading": false, "bulkUploadSubmitting": false}'>
    <h1 class="wt_text-2xl wt_font-bold wt_mb-4"><?php esc_html_e('Bulk Upload Members', 'wicket-acc'); ?></h1>

    <?php if (!$show_bulk_upload) : ?>
        <div class="wt_bg-yellow-100 wt_border wt_border-yellow-400 wt_text-yellow-800 wt_px-4 wt_py-3 wt_rounded-sm wt_mb-4">
            <?php esc_html_e('Bulk upload is currently disabled by configuration.', 'wicket-acc'); ?>
        </div>
    <?php elseif (empty($org_uuid) && count($manageable_organizations) > 1) : ?>
        <p class="wt_text-sm wt_text-content wt_mb-4">
            <?php esc_html_e('Select an organization to continue with bulk member upload.', 'wicket-acc'); ?>
        </p>
        <div class="wt_w-full wt_flex wt_flex-col wt_gap-3" role="list">
            <?php foreach ($manageable_organizations as $organization) :
                $item_query_args = $build_base_query_args();
                $item_query_args['org_uuid'] = (string) $organization['id'];
                ?>
                <a href="<?php echo esc_url(add_query_arg($item_query_args, $bulk_page_url)); ?>"
                    class="button button--secondary component-button wt_inline-flex wt_w-full wt_items-center wt_justify-between wt_p-4"
                    role="listitem">
                    <span><?php echo esc_html((string) $organization['name']); ?></span>
                    <span aria-hidden="true">→</span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php elseif (empty($org_uuid)) : ?>
        <div class="wt_bg-red-100 wt_border wt_border-red-400 wt_text-red-700 wt_px-4 wt_py-3 wt_rounded-sm wt_mb-4">
            <?php esc_html_e('You do not have access to bulk upload members for any organizations.', 'wicket-acc'); ?>
        </div>
    <?php elseif (!in_array($org_uuid, $manageable_org_ids, true)) : ?>
        <div class="wt_bg-red-100 wt_border wt_border-red-400 wt_text-red-700 wt_px-4 wt_py-3 wt_rounded-sm wt_mb-4">
            <?php esc_html_e('You do not have permission to bulk add members for this organization.', 'wicket-acc'); ?>
        </div>
    <?php else : ?>
        <?php
        $bulk_upload_endpoint = \WicketORM\Helpers\template_url() . 'process/bulk-upload-members';
        $bulk_upload_messages_id = 'bulk-upload-messages-' . sanitize_html_class($org_uuid ?: 'default');
        include dirname(__DIR__) . '/templates-partials/members-bulk-upload.php';
        ?>
    <?php endif; ?>
</div>
