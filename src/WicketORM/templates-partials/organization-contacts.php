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

// Server-side query seeds the search signals so state survives a page refresh.
$initial_query = isset($query) ? (string) $query : '';
if ($initial_query === '' && isset($_GET['query'])) {
    $initial_query = sanitize_text_field((string) $_GET['query']);
}
$initial_submitted = $initial_query !== '';

?>
<div
    id="contacts-app-<?php echo esc_attr($org_dom_suffix); ?>"
    data-signals='<?php echo wp_json_encode([
        'contactsLoading' => false,
        'contactsQuery' => $initial_query,
        'contactsSubmitted' => $initial_submitted,
        'addContactModalOpen' => false,
        'addContactSubmitting' => false,
        'addContactSuccess' => false,
        'removeContactModalOpen' => false,
        'removeContactSubmitting' => false,
        'removeContactSuccess' => false,
        'currentRemoveContactUuid' => '',
        'currentRemoveContactName' => '',
        'currentRemoveConnectionIds' => '',
    ]); ?>'
>

    <?php
$contacts ??= null;
$pagination ??= null;
$query ??= '';
include dirname(__FILE__) . '/contacts-list.php';
?>

</div>
