<?php

/**
 * Hypermedia partial for Group update processing.
 */

use WicketORM\Services\GroupService;

if (!defined('ABSPATH')) {
    exit;
}

$request_method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ('POST' !== strtoupper($request_method)) {
    return;
}

$logger = \Wicket()->log();
$log_context = [
    'source' => 'wicket-orgman',
    'action' => 'update_group',
    'user_id' => get_current_user_id(),
];

$nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
if (!$nonce || !wp_verify_nonce($nonce, 'wicket-orgman-update-group')) {
    $logger->warning('Update group invalid nonce', $log_context);
    status_header(200);
    WicketORM\Helpers\DatastarSSE::renderError(__('Invalid or missing security token. Please refresh and try again.', 'wicket-acc'), '#group-update-messages', []);

    return;
}

$group_uuid = isset($_POST['group_uuid']) ? sanitize_text_field(wp_unslash($_POST['group_uuid'])) : '';
$org_uuid = isset($_POST['org_uuid']) ? sanitize_text_field(wp_unslash($_POST['org_uuid'])) : '';

$log_context['group_uuid'] = $group_uuid;
$log_context['org_uuid'] = $org_uuid;
$logger->info('Update group request received', $log_context);

if (empty($group_uuid)) {
    $logger->error('Update group missing group_uuid', $log_context);
    status_header(200);
    WicketORM\Helpers\DatastarSSE::renderError(__('Group identifier missing.', 'wicket-acc'), '#group-update-messages', []);

    return;
}

$group_service = new GroupService();
$current_user = wp_get_current_user();
$access = $group_service->canManageGroup($group_uuid, (string) $current_user->user_login);
if (empty($access['allowed'])) {
    $logger->warning('Update group access denied', $log_context);
    status_header(200);
    WicketORM\Helpers\DatastarSSE::renderError(__('You do not have permission to manage this group.', 'wicket-acc'), '#group-update-messages', []);

    return;
}

$orgman_config = WicketORM\Services\ConfigService::getConfig();
$groups_config = is_array($orgman_config['groups'] ?? null) ? $orgman_config['groups'] : [];
$presentation_config = is_array($groups_config['presentation'] ?? null) ? $groups_config['presentation'] : [];
$editable_fields = is_array($presentation_config['editable_fields'] ?? null) ? $presentation_config['editable_fields'] : [];

$lang = function_exists('wicket_get_current_language') ? wicket_get_current_language() : 'en';
$name_key = 'name_' . $lang;
$desc_key = 'description_' . $lang;

$payload = [];
if (in_array('name', $editable_fields, true)) {
    $group_name = isset($_POST['group_name']) ? sanitize_text_field(wp_unslash($_POST['group_name'])) : '';
    if ($group_name !== '') {
        $payload[$name_key] = $group_name;
    }
}

if (in_array('description', $editable_fields, true)) {
    $group_description = isset($_POST['group_description']) ? sanitize_textarea_field(wp_unslash($_POST['group_description'])) : '';
    $payload[$desc_key] = $group_description;
}

if (empty($payload)) {
    $logger->warning('Update group no editable fields provided', $log_context);
    status_header(200);
    WicketORM\Helpers\DatastarSSE::renderError(__('No editable fields provided.', 'wicket-acc'), '#group-update-messages', []);

    return;
}

if (!function_exists('wicket_api_client')) {
    $logger->error('Update group API client unavailable', $log_context);
    status_header(200);
    WicketORM\Helpers\DatastarSSE::renderError(__('API client unavailable.', 'wicket-acc'), '#group-update-messages', []);

    return;
}

$request_payload = [
    'data' => [
        'type' => 'groups',
        'id' => $group_uuid,
        'attributes' => $payload,
    ],
];

try {
    $client = wicket_api_client();
    $client->patch('groups/' . rawurlencode($group_uuid), ['json' => $request_payload]);
    $logger->info('Update group succeeded', $log_context);
    status_header(200);
    WicketORM\Helpers\DatastarSSE::renderSuccess(__('Group updated successfully.', 'wicket-acc'), '#group-update-messages', []);

    return;
} catch (Throwable $e) {
    $logger->error('Update group failed', array_merge($log_context, [
        'error' => $e->getMessage(),
    ]));
    status_header(200);
    WicketORM\Helpers\DatastarSSE::renderError(__('Failed to update group.', 'wicket-acc'), '#group-update-messages', []);

    return;
}
