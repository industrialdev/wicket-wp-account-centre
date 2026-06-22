<?php

/**
 * Contacts roster wrapper partial.
 *
 * Sets up Datastar signals and includes the contacts list.
 * Mirrors organization-members.php pattern.
 */

if (!defined('ABSPATH')) {
    exit;
}

$org_uuid = isset($org_uuid) ? (string) $org_uuid : '';
if (empty($org_uuid) && isset($_GET['org_uuid'])) {
    $org_uuid = sanitize_text_field((string) $_GET['org_uuid']);
}

if (empty($org_uuid)) {
    return;
}

$org_dom_suffix = sanitize_html_class($org_uuid ?: 'default');

?>
<div
    id="contacts-app-<?php echo esc_attr($org_dom_suffix); ?>"
    data-signals='{
        "contactsLoading": false,
        "addContactModalOpen": false,
        "addContactSubmitting": false,
        "addContactSuccess": false,
        "removeContactModalOpen": false,
        "removeContactSubmitting": false,
        "removeContactSuccess": false,
        "currentRemoveContactUuid": "",
        "currentRemoveContactName": "",
        "currentRemoveConnectionIds": ""
    }'
>

    <?php
    $contacts = $contacts ?? null;
    $pagination = $pagination ?? null;
    $query = $query ?? '';
    include dirname(__FILE__) . '/contacts-list.php';
    ?>

</div>
