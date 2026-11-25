<?php

/**
 * Hypermedia partial that loads current member data for the Edit Permissions modal.
 *
 * The member list renders with lazy loading, so at button-render time the member's
 * description, org-scoped roles, and relationship type are not yet known. The click
 * handler opens the modal with inputs disabled and fetches this endpoint, which
 * patches staged values plus the requested person uuid over SSE; the client applies
 * the staged values only while that uuid still matches the open member, so a stale
 * response for a previously opened member cannot repopulate the new one. Without this
 * fetch, a modal opened from a lazy
 * row shows an empty description, and saving that state clears the stored description.
 *
 * Returns Datastar SSE signal patches.
 */

use starfederation\datastar\ServerSentEventGenerator;
use WicketORM\Helpers\MemberEditDataSignals;
use WicketORM\Helpers\PermissionHelper;
use WicketORM\Services\CacheService;
use WicketORM\Services\ConfigService;
use WicketORM\Services\MemberService;
use WicketORM\Services\MembershipService;

if (!defined('ABSPATH')) {
    exit;
}

$person_uuid = isset($_REQUEST['person_uuid']) ? sanitize_text_field(wp_unslash($_REQUEST['person_uuid'])) : '';
$org_uuid = isset($_REQUEST['org_uuid']) ? sanitize_text_field(wp_unslash($_REQUEST['org_uuid'])) : '';
$membership_uuid = isset($_REQUEST['membership_uuid']) ? sanitize_text_field(wp_unslash($_REQUEST['membership_uuid'])) : '';

// Datastar discards the response body on non-200 statuses, so every failure path
// returns 200 + SSE signal patches (same pattern as update-permissions nonce errors)
// to release the modal's disabled state without leaking member data.
$fail = static function (string $reason) use ($org_uuid, $person_uuid): void {
    \Wicket()->log()->warning('member-edit-data: rejected', [
        'source'      => 'wicket-orgman',
        'reason'      => $reason,
        'org_uuid'    => $org_uuid,
        'person_uuid' => $person_uuid,
    ]);

    $generator = new ServerSentEventGenerator();
    $generator->sendHeaders();
    $generator->patchSignals(MemberEditDataSignals::failurePayload($person_uuid));
    exit;
};

$is_uuid = static function (string $value): bool {
    return (bool) preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/', $value);
};

if (empty($person_uuid) || empty($org_uuid) || empty($membership_uuid)
    || !$is_uuid($person_uuid) || !$is_uuid($org_uuid) || !$is_uuid($membership_uuid)) {
    $fail('invalid_params');
}

if (!is_user_logged_in()
    || !(PermissionHelper::has_active_membership($org_uuid) || PermissionHelper::can_edit_members($org_uuid))) {
    $fail('not_authorized');
}

// Same stale-UUID protection as update-permissions: scope the lookup to the
// membership the org actually resolves to, so a posted membership_uuid from a
// different org cannot leak another org's member data through the auth gate.
$membership_service = new MembershipService();
$resolved_membership_uuid = (string) $membership_service->getMembershipForOrganization($org_uuid);
if ($resolved_membership_uuid === '') {
    $fail('membership_not_found');
}
$membership_uuid = $resolved_membership_uuid;

$config_service = new ConfigService();
$member_service = new MemberService($config_service);

// Same cached full load the lazy-details endpoint uses; the membership generation
// salt keeps this consistent with the list after saves clear the members cache.
$cache_service = new CacheService();
$gen = $cache_service->getMembershipGeneration($membership_uuid);
$cache_key = 'orgman_member_edit_data_' . md5($person_uuid . $org_uuid . $membership_uuid . $gen);
$member = $cache_service->get($cache_key);

if (false === $member) {
    // Release-line readers throw MemberLookupFailedException on transport
    // failures; main returns null. Catch both so the modal degrades to the
    // load-failed state instead of erroring the stream.
    try {
        $member = $member_service->getMemberByPersonUuid($person_uuid, $membership_uuid, $org_uuid);
    } catch (Throwable $e) {
        $fail('reader_exception: ' . $e->getMessage());
    }

    if ($member) {
        $cache_service->set($cache_key, $member);
    }
}

if (!$member || is_wp_error($member)) {
    $fail('member_not_found');
}

$current_roles = [];
if (!empty($member['current_roles']) && is_array($member['current_roles'])) {
    $current_roles = $member['current_roles'];
} elseif (!empty($member['roles']) && is_array($member['roles'])) {
    $current_roles = $member['roles'];
}

$generator = new ServerSentEventGenerator();
$generator->sendHeaders();
$generator->patchSignals(MemberEditDataSignals::successPayload(
    $person_uuid,
    (string) ($member['relationship_description'] ?? ''),
    $current_roles,
    (string) ($member['relationship_type'] ?? '')
));
