<?php

/**
 * Hypermedia partial for Remove Contact modal processing.
 *
 * Processes POST submissions to remove a contact from the contacts roster.
 * New file. Does not modify any existing process handler.
 */

use starfederation\datastar\enums\ElementPatchMode;
use starfederation\datastar\ServerSentEventGenerator;
use WicketORM\Services\ContactService;

if (!defined('ABSPATH')) {
    exit;
}

$request_method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ('POST' !== strtoupper($request_method)) {
    status_header(405);
    echo json_encode(['error' => 'Method not allowed']);

    return;
}

// Validate nonce
$nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
if (!$nonce || !wp_verify_nonce($nonce, 'wicket-orgman-remove-contact')) {
    status_header(200);
    WicketORM\Helpers\DatastarSSE::renderError(
        __('Invalid or missing security token. Please refresh and try again.', 'wicket-acc'),
        '#remove-contact-messages',
        ['removeContactSubmitting' => false, 'contactsLoading' => false]
    );

    return;
}

$org_uuid = isset($_POST['org_uuid']) ? sanitize_text_field(wp_unslash($_POST['org_uuid'])) : '';
$person_uuid = isset($_POST['person_uuid']) ? sanitize_text_field(wp_unslash($_POST['person_uuid'])) : '';
$person_name = isset($_POST['person_name']) ? sanitize_text_field(wp_unslash($_POST['person_name'])) : '';
$connection_ids_csv = isset($_POST['connection_ids']) ? sanitize_text_field(wp_unslash($_POST['connection_ids'])) : '';

if (empty($org_uuid) || empty($person_uuid)) {
    status_header(200);
    WicketORM\Helpers\DatastarSSE::renderError(
        __('Organization and person identifiers are required.', 'wicket-acc'),
        '#remove-contact-messages',
        ['removeContactSubmitting' => false, 'contactsLoading' => false]
    );

    return;
}

// Permission check
if (!WicketORM\Helpers\PermissionHelper::can_manage_contacts($org_uuid)) {
    status_header(200);
    WicketORM\Helpers\DatastarSSE::renderError(
        __('You do not have permission to remove contacts from this organization.', 'wicket-acc'),
        '#remove-contact-messages',
        ['removeContactSubmitting' => false, 'contactsLoading' => false]
    );

    return;
}

// Owner protection
if (WicketORM\Helpers\PermissionHelper::is_contact_removal_prevented($person_uuid, $org_uuid)) {
    status_header(200);
    WicketORM\Helpers\DatastarSSE::renderError(
        __('The organization owner cannot be removed.', 'wicket-acc'),
        '#remove-contact-messages',
        ['removeContactSubmitting' => false, 'contactsLoading' => false]
    );

    return;
}

// Parse connection IDs
$connection_ids = [];
if ($connection_ids_csv !== '') {
    $connection_ids = array_values(array_filter(array_map('sanitize_text_field', explode(',', $connection_ids_csv))));
}

try {
    $contactService = new ContactService();
    $result = $contactService->removeContact($org_uuid, $person_uuid, [
        'connection_ids' => $connection_ids,
    ]);

    if (is_wp_error($result)) {
        status_header(200);
        WicketORM\Helpers\DatastarSSE::renderError(
            $result->get_error_message(),
            '#remove-contact-messages',
            ['removeContactSubmitting' => false, 'contactsLoading' => false]
        );

        return;
    }

    // Build success message
    $display_name = $person_name !== '' ? $person_name : __('the contact', 'wicket-acc');
    $success_message = sprintf(
        esc_html__('Successfully removed %1$s from the contact list.', 'wicket-acc'),
        '<strong>' . esc_html($display_name) . '</strong>'
    );

    if (!empty($result['membership_preserved'])) {
        $success_message .= ' ' . esc_html__('Roles preserved due to active membership.', 'wicket-acc');
    }

    // Refresh the contacts list
    $contacts_list_url = WicketORM\Helpers\template_url() . 'contacts-list';
    $separator = str_contains($contacts_list_url, '?') ? '&' : '?';
    $refresh_url = $contacts_list_url . $separator . http_build_query([
        'org_uuid' => $org_uuid,
        'page'     => 1,
    ]);

    $generator = new ServerSentEventGenerator();
    $generator->sendHeaders();
    $generator->patchSignals([
        'removeContactSubmitting' => false,
        'removeContactSuccess'    => true,
        'contactsLoading'         => false,
    ]);

    // Success message
    $success_html = sprintf(
        '<div class="wt_bg-green-100 wt_border wt_border-green-400 wt_text-green-700 wt_px-4 wt_py-3 wt_rounded-sm wt_mb-4"><p><strong>%1$s</strong></p><p>%2$s</p></div>',
        esc_html__('Success!', 'wicket-acc'),
        wp_kses_post($success_message)
    );
    $generator->patchElements($success_html, [
        'selector' => '#remove-contact-messages',
        'mode'     => ElementPatchMode::Inner,
    ]);

    // Refresh contacts list
    $contacts_result = $contactService->getContacts($org_uuid, ['page' => 1]);
    ob_start();
    $contacts = $contacts_result['contacts'] ?? [];
    $pagination = $contacts_result['pagination'] ?? [];
    include dirname(__DIR__) . '/contacts-list.php';
    $contacts_html = ob_get_clean();

    $generator->patchElements((string) $contacts_html, [
        'selector' => '#contacts-list-container-' . sanitize_html_class($org_uuid),
        'mode'     => ElementPatchMode::Outer,
    ]);

    return;

} catch (Throwable $e) {
    \Wicket()->log()->error('remove-contact failed: ' . $e->getMessage(), [
        'source'      => 'wicket-orgman',
        'org_uuid'    => $org_uuid,
        'person_uuid' => $person_uuid,
    ]);
    status_header(200);
    WicketORM\Helpers\DatastarSSE::renderError(
        __('An unexpected error occurred. Please try again.', 'wicket-acc'),
        '#remove-contact-messages',
        ['removeContactSubmitting' => false, 'contactsLoading' => false]
    );

    return;
}
