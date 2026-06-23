<?php

declare(strict_types=1);

namespace WicketORM\Templates;

use starfederation\datastar\ServerSentEventGenerator;
use WicketORM\Services\CacheService;
use WicketORM\Services\ConfigService;
use WicketORM\Services\MemberService;

// Ensure this file is not accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Lazy loading endpoint for member card cosmetic details.
 * Returns Datastar SSE fragments.
 */
$person_uuid = isset($_REQUEST['person_uuid']) ? sanitize_text_field($_REQUEST['person_uuid']) : '';
$org_uuid = isset($_REQUEST['org_uuid']) ? sanitize_text_field($_REQUEST['org_uuid']) : '';
$membership_uuid = isset($_REQUEST['membership_uuid']) ? sanitize_text_field($_REQUEST['membership_uuid']) : '';
$mode = isset($_REQUEST['mode']) ? sanitize_text_field($_REQUEST['mode']) : '';
$group_uuid = isset($_REQUEST['group_uuid']) ? sanitize_text_field($_REQUEST['group_uuid']) : '';

if (empty($person_uuid) || empty($org_uuid)) {
    exit;
}

$config_service = new ConfigService();
$member_service = new MemberService($config_service);

// Fetch the member with full details (lazy = false)
// Uses CacheService so the versioned cache_salt key invalidates this alongside the member list.
$cache_service = new CacheService();
$gen = $cache_service->getMembershipGeneration($membership_uuid);
$cache_key = 'orgman_lazy_details_' . md5($person_uuid . $org_uuid . $membership_uuid . $mode . $group_uuid . $gen);
$member = $cache_service->get($cache_key);

if (false === $member) {
    $member = $member_service->getMemberByPersonUuid($person_uuid, $membership_uuid, $org_uuid);

    if ($member) {
        $cache_service->set($cache_key, $member);
    }
}

// Initialize Datastar SSE Generator. sendHeaders() sets Content-Type: text/event-stream
// (and keep-alive on HTTP/1.1); must be called before any output so Datastar's client-side
// fetch can parse the stream instead of treating it as raw HTML.
$generator = new ServerSentEventGenerator();
$generator->sendHeaders();
$person_uuid_no_dashes = str_replace('-', '', $person_uuid);

// If the member was filtered out by the full load (e.g. relationship filters), remove the card.
// For groups mode, the member may not exist in the org membership endpoint (group members
// live in /group_members, not /organization_memberships/person_memberships). Instead of
// removing the card, do a best-effort render from the person API and the group member data.
if (!$member) {
    if ($mode === 'groups') {
        \Wicket()->log()->info('member-details: Group member not in membership, using fallback', [
            'source' => 'wicket-orgman',
            'person_uuid' => $person_uuid,
            'org_uuid' => $org_uuid,
            'group_uuid' => $group_uuid,
        ]);
        // Build a minimal member array from person API (confirmed_at) and group role.
        $member = [
            'lazy_loaded' => true,
            'is_confirmed' => false,
            'roles' => [],
            'current_roles' => [],
        ];
        if ($member_service) {
            try {
                $person = $member_service->getPersonById($person_uuid);
                $personAttrs = is_array($person) ? ($person['data']['attributes'] ?? []) : [];
                $member['confirmed_at'] = $personAttrs['user']['confirmed_at']
                    ?? $personAttrs['confirmed_at']
                    ?? null;
                $member['is_confirmed'] = !empty($member['confirmed_at']);
                $member['email'] = $personAttrs['email']
                    ?? $personAttrs['primary_email_address']
                    ?? '';
                $member['full_name'] = trim(
                    ($personAttrs['given_name'] ?? '') . ' ' . ($personAttrs['family_name'] ?? '')
                );
            } catch (\Throwable $e) {
                \Wicket()->log()->warning('member-details: Person API fallback failed', [
                    'person_uuid' => $person_uuid,
                    'error' => $e->getMessage(),
                ]);
                // Card has useful placeholder content; do not remove it.
                exit;
            }
        }
    } else {
        \Wicket()->log()->info('member-details: Member not found, removing card', [
            'source' => 'wicket-orgman',
            'person_uuid'     => $person_uuid,
            'org_uuid'        => $org_uuid,
            'membership_uuid' => $membership_uuid,
        ]);
        // Delete the entire card container
        $generator->removeElements('#member-' . $person_uuid_no_dashes);
        exit;
    }
}

// Mark as lazy loaded for the template logic
$member['lazy_loaded'] = true;
// Derive is_confirmed from confirmed_at (the service never populates is_confirmed directly).
$member['is_confirmed'] = !empty($member['confirmed_at']);

// Shared variables for the partials
$config = ConfigService::getConfig();
$member_list_config = $config['presentation']['member_list'] ?? [];
$show_account_status = (bool) ($member_list_config['account_status']['enabled'] ?? true);
$show_unconfirmed_label = (bool) ($member_list_config['account_status']['show_unconfirmed_label'] ?? true);
$unconfirmed_label = (string) ($member_list_config['account_status']['unconfirmed_label'] ?? __('Account not confirmed', 'wicket-acc'));
$confirmed_tooltip = (string) ($member_list_config['account_status']['confirmed_tooltip'] ?? __('Account confirmed', 'wicket-acc'));
$unconfirmed_tooltip = (string) ($member_list_config['account_status']['unconfirmed_tooltip'] ?? __('Account not confirmed', 'wicket-acc'));
$member_email = $member['email'] ?? '';

// Fragment 1: Update Status Indicator for ALL instances of this member
ob_start();
?>
<div id="member-status-<?php echo esc_attr($person_uuid_no_dashes); ?>" class="wt_inline-flex wt_items-center" data-member-status="<?php echo esc_attr($person_uuid_no_dashes); ?>">
    <?php if ($show_account_status) : ?>
        <?php if (!empty($member['is_confirmed'])) : ?>
            <span class="wt_text-content" title="<?php echo esc_attr($confirmed_tooltip); ?>">
                <span class="wt_inline-block wt_w-2 wt_h-2 wt_rounded-full wt_bg-green-500" aria-hidden="true"></span>
            </span>
        <?php else : ?>
            <span class="wt_text-content" title="<?php echo esc_attr($unconfirmed_tooltip); ?>">
                <span class="wt_inline-block wt_w-2 wt_h-2 wt_rounded-full wt_bg-gray-400" aria-hidden="true"></span>
            </span>
            <?php if ($show_unconfirmed_label && $unconfirmed_label !== '') : ?>
                <span class="wt_text-warning wt_whitespace-nowrap wt_ml-1 wt_text-2xs" title="<?php echo esc_attr($unconfirmed_tooltip); ?>">
                    <?php echo esc_html($unconfirmed_label); ?>
                </span>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php
$status_html = ob_get_clean();
// Patch ALL elements with this data-member-status attribute
$generator->patchElements($status_html, ['selector' => '[data-member-status="' . $person_uuid_no_dashes . '"]']);

// Fragment 2: Update Details Block (Roles, Relationships, Email)
$role_display_map = (array) ($member_list_config['display_roles']['labels'] ?? []);
$current_roles = !empty($member['current_roles']) ? $member['current_roles'] : ($member['roles'] ?? []);
$formatted_roles = array_map(static function ($role) use ($role_display_map) {
    if (isset($role_display_map[$role])) {
        return $role_display_map[$role];
    }

    return ucwords(str_replace('_', ' ', (string) $role));
}, is_array($current_roles) ? $current_roles : []);
$roles_text = !empty($formatted_roles) ? implode(', ', $formatted_roles) : '—';

ob_start();
?>
<div id="member-details-<?php echo esc_attr($person_uuid_no_dashes); ?>" class="wt_flex wt_flex-col wt_gap-2" data-member-details="<?php echo esc_attr($person_uuid_no_dashes); ?>">
    <?php
    $has_details = false;
if (!empty($member['relationship_description']) && \WicketORM\Helpers\Helper::should_show_member_description()) :
    $has_details = true;
    ?>
        <p class="member-description wt_text-sm wt_text-content wt_mb-0">
            <?php echo esc_html($member['relationship_description']); ?>
        </p>
    <?php endif; ?>
    <?php if (!empty($member['relationship_names']) && \WicketORM\Helpers\Helper::should_show_member_relationship_type()) :
        $has_details = true;
        ?>
        <div class="wt_flex wt_items-center wt_gap-2">
            <span class="wt_text-content"><?php echo esc_html($member['relationship_names']); ?></span>
        </div>
    <?php endif; ?>
    <?php if (!empty($member_email) && \WicketORM\Helpers\Helper::should_show_member_email()) :
        $has_details = true;
        ?>
        <div class="wt_flex wt_items-center wt_gap-2">
            <a href="mailto:<?php echo esc_attr($member_email); ?>" class="wt_text-sm wt_text-interactive wt_hover_underline">
                <?php echo esc_html($member_email); ?>
            </a>
        </div>
    <?php endif; ?>
    <?php if (\WicketORM\Helpers\Helper::should_show_member_roles()) :
        $has_details = true;
        ?>
        <div class="wt_flex wt_items-baseline wt_gap-2 wt_text-sm">
            <strong><?php esc_html_e('Role(s):', 'wicket-acc'); ?></strong>
            <span class="wt_text-content"><?php echo esc_html($roles_text); ?></span>
        </div>
    <?php endif; ?>

    <?php if (!$has_details) : ?>
        <div class="wt_text-content wt_text-secondary wt_text-sm wt_italic" data-empty-details="true">
            <?php esc_html_e('No additional details available', 'wicket-acc'); ?>
        </div>
    <?php endif; ?>
</div>
<?php
$details_html = ob_get_clean();
// Patch ALL elements with this data-member-details attribute
$generator->patchElements($details_html, ['selector' => '[data-member-details="' . $person_uuid_no_dashes . '"]']);
