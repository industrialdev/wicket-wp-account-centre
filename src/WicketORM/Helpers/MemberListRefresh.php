<?php

namespace WicketORM\Helpers;

use starfederation\datastar\enums\ElementPatchMode;

if (!defined('ABSPATH') && !defined('WICKET_DOING_TESTS')) {
    exit;
}

/**
 * Shared helper for building Datastar element patches that refresh org member lists.
 */
class MemberListRefresh
{
    /**
     * Build a members-list replacement patch for an organization list container.
     *
     * @param string $orgUuid Organization UUID.
     * @param string $membershipUuid Organization membership UUID.
     * @param string $orgDomSuffix DOM suffix used by the list container id.
     * @param int    $page Page number to render.
     * @param string $query Search query for members list.
     * @return array<int, array{elements:string, selector:string, mode:ElementPatchMode}>
     */
    public static function buildOrgMembersListPatches(
        string $orgUuid,
        string $membershipUuid,
        string $orgDomSuffix,
        int $page = 1,
        string $query = ''
    ): array {
        if ($orgUuid === '') {
            return [];
        }

        $safe_dom_suffix = sanitize_html_class($orgDomSuffix !== '' ? $orgDomSuffix : ($orgUuid ?: 'default'));
        $members_list_target = 'members-list-container-' . $safe_dom_suffix;
        $original_get = $_GET;

        try {
            $_GET['org_uuid'] = $orgUuid;
            $_GET['page'] = (string) max(1, $page);
            $_GET['query'] = $query;

            if ($membershipUuid !== '') {
                $_GET['membership_uuid'] = $membershipUuid;
            } else {
                unset($_GET['membership_uuid']);
            }

            // Seed expected locals before including the partial.
            $membership_uuid = $membershipUuid;
            $members = null;
            $pagination = null;

            ob_start();
            include dirname(__DIR__) . '/templates-partials/members-list.php';
            $members_list_html = (string) ob_get_clean();
        } finally {
            $_GET = $original_get;
        }

        if ($members_list_html === '') {
            return [];
        }

        return [[
            'elements' => $members_list_html,
            'selector' => '#' . $members_list_target,
            'mode' => ElementPatchMode::Outer,
        ]];
    }
}
