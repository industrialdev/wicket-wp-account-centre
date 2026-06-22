<?php

/**
 * Hypermedia partial for Add Contact modal processing.
 *
 * Processes POST submissions to add a contact to the contacts roster.
 * New file. Does not modify any existing process handler.
 */

use starfederation\datastar\enums\ElementPatchMode;
use starfederation\datastar\ServerSentEventGenerator;
use WicketORM\Services\ConfigService;
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

$org_dom_suffix = isset($_POST['org_dom_suffix'])
    ? sanitize_html_class((string) wp_unslash($_POST['org_dom_suffix']))
    : 'default';
$error_signals = [
    'addContactSubmitting' => false,
    'contactsLoading'      => false,
    'addContactSuccess'    => false,
];

// Validate nonce
$nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
if (!$nonce || !wp_verify_nonce($nonce, 'wicket-orgman-add-contact')) {
    status_header(200);
    WicketORM\Helpers\DatastarSSE::renderError(
        __('Invalid or missing security token. Please refresh and try again.', 'wicket-acc'),
        '#add-contact-messages-' . $org_dom_suffix,
        $error_signals
    );

    return;
}

$org_uuid = isset($_POST['org_uuid']) ? sanitize_text_field(wp_unslash($_POST['org_uuid'])) : '';
if (empty($org_uuid)) {
    status_header(200);
    WicketORM\Helpers\DatastarSSE::renderError(
        __('Organization identifier missing.', 'wicket-acc'),
        '#add-contact-messages-' . $org_dom_suffix,
        $error_signals
    );

    return;
}

// Permission check
if (!WicketORM\Helpers\PermissionHelper::can_manage_contacts($org_uuid)) {
    status_header(200);
    WicketORM\Helpers\DatastarSSE::renderError(
        __('You do not have permission to add contacts to this organization.', 'wicket-acc'),
        '#add-contact-messages-' . $org_dom_suffix,
        $error_signals
    );

    return;
}

$contact_data = [
    'first_name'        => isset($_POST['first_name']) ? sanitize_text_field(wp_unslash($_POST['first_name'])) : '',
    'last_name'         => isset($_POST['last_name']) ? sanitize_text_field(wp_unslash($_POST['last_name'])) : '',
    'email'             => isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '',
    'relationship_type' => isset($_POST['relationship_type']) ? sanitize_key(wp_unslash($_POST['relationship_type'])) : '',
];

$roles = [];
if (isset($_POST['roles']) && is_array($_POST['roles'])) {
    $roles = array_map(static function ($role) {
        return sanitize_text_field(wp_unslash($role));
    }, $_POST['roles']);
}

// Validate required fields
if ($contact_data['first_name'] === '' || $contact_data['last_name'] === '' || $contact_data['email'] === '') {
    status_header(200);
    WicketORM\Helpers\DatastarSSE::renderError(
        __('First name, last name, and email are required.', 'wicket-acc'),
        '#add-contact-messages-' . $org_dom_suffix,
        $error_signals
    );

    return;
}

if ($contact_data['relationship_type'] === '') {
    status_header(200);
    WicketORM\Helpers\DatastarSSE::renderError(
        __('Relationship type is required.', 'wicket-acc'),
        '#add-contact-messages-' . $org_dom_suffix,
        $error_signals
    );

    return;
}

try {
    $contactService = new ContactService();

    $result = $contactService->addContact($org_uuid, $contact_data, [
        'roles' => $roles,
    ]);

    if (is_wp_error($result)) {
        status_header(200);
        WicketORM\Helpers\DatastarSSE::renderError(
            $result->get_error_message(),
            '#add-contact-messages-' . $org_dom_suffix,
            array_merge($error_signals, ['addContactFormError' => true])
        );

        return;
    }

    // Build success message
    $full_name = trim($contact_data['first_name'] . ' ' . $contact_data['last_name']);
    $success_message = wp_sprintf(
        /* translators: 1: contact full name, 2: contact email */
        __('Successfully added %1$s with email %2$s.', 'wicket-acc'),
        $full_name !== '' ? $full_name : __('the contact', 'wicket-acc'),
        $contact_data['email']
    );

    // Append warnings if any
    $warnings = $result['warnings'] ?? [];
    if (!empty($warnings)) {
        $success_message .= ' ' . implode(' ', array_map(static function ($w) {
            return '<span class="wt_text-yellow-700">' . esc_html($w) . '</span>';
        }, $warnings));
    }

    // Refresh the contacts list
    $contacts_result = $contactService->getContacts($org_uuid, ['page' => 1]);
    ob_start();
    $contacts = $contacts_result['contacts'] ?? [];
    $pagination = $contacts_result['pagination'] ?? [];
    include dirname(__DIR__) . '/contacts-list.php';
    $contacts_html = ob_get_clean();

    status_header(200);
    $generator = new ServerSentEventGenerator();
    $generator->sendHeaders();
    $generator->patchSignals([
        'addContactSubmitting' => false,
        'contactsLoading'      => false,
        'addContactSuccess'    => true,
    ]);

    // Success message
    $success_html = sprintf(
        '<div class="wt_bg-green-100 wt_border wt_border-green-400 wt_text-green-700 wt_px-4 wt_py-3 wt_rounded-sm wt_mb-4"><p><strong>%1$s</strong></p><p>%2$s</p></div>',
        esc_html__('Success!', 'wicket-acc'),
        wp_kses_post($success_message)
    );
    $generator->patchElements($success_html, [
        'selector' => '#add-contact-messages-' . $org_dom_suffix,
        'mode'     => ElementPatchMode::Inner,
    ]);

    // Patch the contacts list
    $generator->patchElements((string) $contacts_html, [
        'selector' => '#contacts-list-container-' . sanitize_html_class($org_uuid),
        'mode'     => ElementPatchMode::Outer,
    ]);

    return;

} catch (Throwable $e) {
    \Wicket()->log()->error('add-contact failed: ' . $e->getMessage(), [
        'source'   => 'wicket-orgman',
        'org_uuid' => $org_uuid,
    ]);
    status_header(200);
    WicketORM\Helpers\DatastarSSE::renderError(
        __('An unexpected error occurred. Please try again.', 'wicket-acc'),
        '#add-contact-messages-' . $org_dom_suffix,
        $error_signals
    );

    return;
}
