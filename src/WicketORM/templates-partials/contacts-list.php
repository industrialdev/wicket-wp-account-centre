<?php

declare(strict_types=1);

use WicketORM\Helpers as OrgHelpers;

/*
 * Contacts list Datastar partial.
 *
 * Mirrors members-list.php structure but for relationship-based contacts.
 * Expects: $org_uuid, $contacts, $pagination, $query (all optional).
 */

if (!defined('ABSPATH')) {
    exit;
}

$org_uuid = isset($org_uuid) ? (string) $org_uuid : '';
if (empty($org_uuid) && isset($_GET['org_uuid'])) {
    $org_uuid = sanitize_text_field((string) $_GET['org_uuid']);
}

$contacts_list_target = 'contacts-list-container-' . sanitize_html_class($org_uuid ?: 'default');

$orgman_config = WicketORM\Services\ConfigService::getConfig();
$contacts_config = $orgman_config['contacts'] ?? [];
$page_size = (int) ($contacts_config['presentation']['page_size'] ?? 10);
$type_labels = $contacts_config['form']['relationship_type'] ?? [];
$permission_labels = $contacts_config['form']['permissions'] ?? [];
$assign_roles = $contacts_config['on_add']['assign_roles'] ?? [];

$contacts_list_endpoint = OrgHelpers\template_url() . 'contacts-list';
$add_contact_endpoint = OrgHelpers\template_url() . 'process/add-contact';
$remove_contact_endpoint = OrgHelpers\template_url() . 'process/remove-contact';

$page = isset($pagination['currentPage']) ? (int) $pagination['currentPage'] : 1;
$total_pages = isset($pagination['totalPages']) ? (int) $pagination['totalPages'] : 1;
$total_items = isset($pagination['totalItems']) ? (int) $pagination['totalItems'] : 0;
$query = isset($query) ? (string) $query : '';

// Fetch contacts if not already provided
if ((!isset($contacts) || !is_array($contacts)) && !empty($org_uuid)) {
    $contactService = new WicketORM\Services\ContactService();
    $page = max(1, isset($_GET['page']) ? (int) $_GET['page'] : 1);
    $query = isset($_GET['query']) ? sanitize_text_field((string) $_GET['query']) : $query;

    $result = $contactService->getContacts($org_uuid, [
        'page'  => $page,
        'size'  => $page_size,
        'query' => $query,
    ]);

    $contacts = $result['contacts'] ?? [];
    $pagination = $result['pagination'] ?? [];
    $page = (int) ($pagination['currentPage'] ?? $page);
    $total_pages = (int) ($pagination['totalPages'] ?? $total_pages);
    $total_items = (int) ($pagination['totalItems'] ?? $total_items);
}

$contacts = isset($contacts) && is_array($contacts) ? $contacts : [];
$total_pages = max(1, $total_pages);
$page = min(max(1, $page), $total_pages);

$org_dom_suffix = sanitize_html_class($org_uuid ?: 'default');
$show_remove_button = OrgHelpers\PermissionHelper::can_manage_contacts($org_uuid);
$can_add = OrgHelpers\PermissionHelper::can_manage_contacts($org_uuid);

$remove_contact_reset_actions = "(() => { const modal = document.getElementById('removeContactModal'); const messages = modal ? modal.querySelector('#remove-contact-messages') : document.getElementById('remove-contact-messages'); if (messages) messages.innerHTML = ''; if (modal && modal.open) modal.close(); })(); \$removeContactModalOpen = false; \$removeContactSubmitting = false; \$removeContactSuccess = false; \$contactsLoading = false; \$currentRemoveContactUuid = ''; \$currentRemoveContactName = ''; \$currentRemoveConnectionIds = '';";
$remove_contact_success_actions = "console.log('Contact removed successfully'); \$removeContactSubmitting = false; \$removeContactSuccess = true; \$contactsLoading = false;";
$remove_contact_error_actions = "console.error('Failed to remove contact'); \$removeContactSubmitting = false; \$contactsLoading = false;";

// Pagination URL builder
$build_url = static function (int $page_number) use ($contacts_list_endpoint, $org_uuid, $query) {
    $args = [
        'org_uuid' => $org_uuid,
        'query'    => $query,
        'page'     => $page_number,
    ];
    $separator = str_contains($contacts_list_endpoint, '?') ? '&' : '?';

    return $contacts_list_endpoint . $separator . http_build_query($args, '', '&', PHP_QUERY_RFC3986);
};

$build_action = static function (int $page_number) use ($build_url) {
    $url = $build_url($page_number);

    return '$contactsLoading = true; @get(\'' . $url . '\')';
};

?>
<div
    id="<?php echo esc_attr($contacts_list_target); ?>"
    class="wt_mt-6 wt_mb-6 wt_flex wt_flex-col wt_gap-1 wt_relative"
    data-page="<?php echo esc_attr((string) $page); ?>"
    data-attr:aria-busy="$contactsLoading">

    <div class="contacts-loading-state wt_flex wt_flex-col wt_items-center wt_justify-center wt_gap-4 wt_rounded-card wt_border wt_border-color wt_bg-white wt_shadow-sm wt_text-center"
        data-show="$contactsLoading"
        style="display: none;">
        <span class="wt_loader" aria-hidden="true"></span>
        <p class="wt_text-base wt_font-semibold wt_text-content wt_leading-normal" role="status" aria-live="polite">
            <?php esc_html_e('Processing. Please wait...', 'wicket-acc'); ?>
        </p>
    </div>

    <div data-show="!$contactsLoading">

        <div class="wt_text-xl wt_font-semibold wt_mb-3">
            <?php esc_html_e('Number of assigned people:', 'wicket-acc'); ?>
            <?php echo (int) $total_items; ?>
        </div>

        <!-- Search -->
        <div class="wt_mb-4 wt_flex wt_gap-2">
            <form class="wt_flex wt_w-full wt_gap-2" method="GET"
                data-on:submit="$contactsLoading = true; @get('<?php echo esc_js($build_url(1)); ?>&query=' + encodeURIComponent(evt.target.querySelector('input[name=query]').value)); evt.preventDefault();">
                <input type="text" name="query" value="<?php echo esc_attr($query); ?>"
                    class="wt_w-full wt_border wt_border-color wt_rounded-md wt_p-2 wt_text-sm"
                    placeholder="<?php esc_attr_e('Search by name or email...', 'wicket-acc'); ?>">
                <button type="submit" class="button button--primary wt_px-4 wt_py-2 wt_text-sm component-button wt_whitespace-nowrap">
                    <?php esc_html_e('Search', 'wicket-acc'); ?>
                </button>
            </form>
        </div>

        <?php if (empty($contacts)): ?>
            <p class="wt_text-gray-500 wt_p-4"><?php esc_html_e('No contacts found.', 'wicket-acc'); ?></p>
        <?php else: ?>
            <?php foreach ($contacts as $contact):
                $person_uuid = $contact['person_uuid'] ?? '';
                $person_uuid_safe = $person_uuid ? str_replace('-', '', $person_uuid) : uniqid('contact', true);
                $contact_name = $contact['full_name'] ?? '';
                $contact_email = $contact['email'] ?? '';
                $type_names_csv = $contact['relationship_type_names_csv'] ?? '';
                $connection_ids_csv = $contact['connection_ids_csv'] ?? '';
                ?>
            <div class="contact-card wt_bg-light-neutral wt_rounded-card wt_p-6 wt_transition-opacity wt_duration-300"
                id="contact-card-<?php echo esc_attr($person_uuid_safe); ?>">
                <div class="wt_flex wt_w-full md_wt_flex-row wt_items-start wt_justify-between wt_gap-4">
                    <div class="wt_flex wt_flex-col wt_gap-2 wt_w-full md_wt_w-4-5">
                        <div class="wt_flex wt_flex-col sm_wt_flex-row wt_items-start sm_wt_items-center wt_gap-2">
                            <h3 class="wt_text-xl wt_font-medium wt_text-content wt_mb-0">
                                <?php echo esc_html($contact_name); ?>
                            </h3>
                        </div>
                        <div class="wt_flex wt_flex-col wt_gap-2">
                            <?php if ($contact_email !== ''): ?>
                            <div class="wt_flex wt_items-center wt_gap-2">
                                <a href="mailto:<?php echo esc_attr($contact_email); ?>" class="wt_text-sm wt_text-interactive wt_hover_underline">
                                    <?php echo esc_html($contact_email); ?>
                                </a>
                            </div>
                            <?php endif; ?>
                            <?php if ($type_names_csv !== ''): ?>
                            <div class="wt_flex wt_items-center wt_gap-2 wt_text-sm">
                                <strong><?php esc_html_e('Relationship:', 'wicket-acc'); ?></strong>
                                <span class="wt_text-content"><?php echo esc_html($type_names_csv); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="wt_flex wt_flex-col sm_wt_flex-row wt_items-stretch sm_wt_items-start wt_gap-2 wt_shrink-0">
                        <?php if ($show_remove_button): ?>
                        <button type="button" class="acc-remove-button remove-contact-button button button--secondary wt_inline-flex wt_items-center wt_justify-between wt_gap-2 wt_px-4 wt_py-2 wt_bg-light-neutral wt_text-sm wt_border wt_border-bg-interactive wt_transition-colors wt_whitespace-nowrap component-button"
                            data-on:click="
                                $removeContactSuccess = false;
                                $removeContactSubmitting = false;
                                (() => { const messages = document.getElementById('remove-contact-messages'); if (messages) messages.innerHTML = ''; })();
                                $currentRemoveContactUuid = '<?php echo esc_js($person_uuid); ?>';
                                $currentRemoveContactName = '<?php echo esc_js($contact_name); ?>';
                                $currentRemoveConnectionIds = '<?php echo esc_js($connection_ids_csv); ?>';
                                $removeContactModalOpen = true
                            ">
                            <?php esc_html_e('Remove', 'wicket-acc'); ?>
                            <svg class="wt_w-4 wt_h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M19 7l-.867 12.142A2 2 0 0 1 16.138 21H7.862a2 2 0 0 1-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v3M4 7h16" />
                            </svg>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- Pagination -->
        <nav class="contacts-pagination wt_mt-6 wt_flex wt_flex-col wt_gap-4" aria-label="<?php esc_attr_e('Contacts pagination', 'wicket-acc'); ?>">
            <div class="contacts-pagination__info wt_w-full wt_text-left wt_text-sm wt_text-content">
                <?php
                    if ($total_items > 0) {
                        $first = (($page - 1) * $page_size) + 1;
                        $last = min($total_items, $page * $page_size);
                        echo esc_html(sprintf(__('Showing %1$d-%2$d of %3$d', 'wicket-acc'), $first, $last, $total_items));
                    }
?>
            </div>
            <?php if ($total_pages > 1): ?>
            <div class="contacts-pagination__controls wt_w-full wt_flex wt_items-center wt_gap-2 wt_justify-end wt_self-end">
                <?php if ($page > 1): ?>
                    <button type="button"
                        class="button button--secondary wt_px-3 wt_py-2 wt_text-sm component-button"
                        data-on:click="<?php echo esc_attr($build_action($page - 1)); ?>"
                        data-on:success="<?php echo esc_attr('$contactsLoading = false; ' . wp_sprintf("select('#%s') | set(html)", $contacts_list_target)); ?>"
                        data-indicator:contacts-loading>
                        <?php esc_html_e('Previous', 'wicket-acc'); ?>
                    </button>
                <?php endif; ?>
                <div class="wt_flex wt_items-center wt_gap-1">
                    <?php for ($i = 1; $i <= $total_pages; $i++):
                        $is_current = ($i === $page);
                        ?>
                        <button type="button"
                            class="button wt_px-3 wt_py-2 wt_text-sm <?php echo $is_current ? 'button--primary' : 'button--secondary'; ?> component-button"
                            <?php if ($is_current): ?>disabled<?php endif; ?>
                            <?php if (!$is_current): ?>data-on:click="<?php echo esc_attr($build_action($i)); ?>" <?php endif; ?>
                            data-on:success="<?php echo esc_attr('$contactsLoading = false; ' . wp_sprintf("select('#%s') | set(html)", $contacts_list_target)); ?>"
                            data-indicator:contacts-loading>
                            <?php echo esc_html((string) $i); ?>
                        </button>
                    <?php endfor; ?>
                </div>
                <?php if ($page < $total_pages): ?>
                    <button type="button"
                        class="button button--secondary wt_px-3 wt_py-2 wt_text-sm component-button"
                        data-on:click="<?php echo esc_attr($build_action($page + 1)); ?>"
                        data-on:success="<?php echo esc_attr('$contactsLoading = false; ' . wp_sprintf("select('#%s') | set(html)", $contacts_list_target)); ?>"
                        data-indicator:contacts-loading>
                        <?php esc_html_e('Next', 'wicket-acc'); ?>
                    </button>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </nav>

        <?php if ($can_add): ?>
        <div class="wt_mt-6">
            <button type="button"
                class="button button--primary add-contact-button wt_w-full wt_py-2 component-button"
                data-on:click="$addContactSuccess = false; $addContactSubmitting = false; (() => { const modal = document.getElementById('addContactModal'); const form = modal ? modal.querySelector('form') : null; if (form && form.reset) form.reset(); const messages = document.querySelector('[id^='add-contact-messages-']'); if (messages) messages.innerHTML = ''; })(); $addContactModalOpen = true">
                <?php esc_html_e('Add Individual Contact', 'wicket-acc'); ?>
            </button>
        </div>
        <?php endif; ?>

    </div>
</div>

<!-- Add Contact Modal -->
<?php if ($can_add): ?>
<div data-signals='{"addContactModalOpen": false, "addContactSubmitting": false, "addContactSuccess": false}'>
    <dialog id="addContactModal" class="modal wt_m-auto max_wt_3xl wt_rounded-md wt_shadow-md backdrop_wt_bg-black-50"
        data-show="$addContactModalOpen"
        data-effect="if ($addContactModalOpen) el.showModal(); else el.close();"
        data-on:close="$addContactModalOpen = false; $addContactSubmitting = false;">
        <div class="wt_bg-white wt_p-6 wt_relative">
            <button type="button" class="orgman-modal__close wt_absolute wt_right-4 wt_top-4 wt_text-lg wt_font-semibold"
                data-on:click="$addContactModalOpen = false;"
                data-show="!$addContactSuccess">
                x
            </button>

            <h2 class="wp-block-heading has-heading-sm-font-size wt_text-2xl wt_font-semibold wt_mb-4">
                <?php esc_html_e('Add Contact', 'wicket-acc'); ?>
            </h2>

            <div id="add-contact-messages-<?php echo esc_attr($org_dom_suffix); ?>">
                <!-- Messages inserted here by Datastar -->
            </div>

            <form method="POST"
                data-show="!$addContactSuccess"
                data-on:submit="if(!$addContactSubmitting){ $addContactSubmitting = true; $contactsLoading = true; @post('<?php echo esc_js($add_contact_endpoint); ?>', { contentType: 'form' }); }"
                data-on:submit__prevent-default="true"
                data-on:success="$addContactSubmitting = false; $contactsLoading = false; $addContactSuccess = true;"
                data-on:error="console.error('Failed to add contact'); $addContactSubmitting = false; $contactsLoading = false;"
                data-on:reset="$addContactSubmitting = false; $contactsLoading = false">

                <input type="hidden" name="org_uuid" value="<?php echo esc_attr($org_uuid); ?>">
                <input type="hidden" name="org_dom_suffix" value="<?php echo esc_attr($org_dom_suffix); ?>">
                <input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce('wicket-orgman-add-contact')); ?>">

                <div class="wt_grid wt_grid-cols-2 wt_gap-4 wt_mb-4">
                    <div>
                        <label class="wt_block wt_text-sm wt_font-medium wt_mb-2" for="contact-first-name">
                            <?php esc_html_e('First Name', 'wicket-acc'); ?> *
                        </label>
                        <input type="text" id="contact-first-name" name="first_name" required
                            class="wt_w-full wt_border wt_border-color wt_rounded-md wt_p-2 wt_text-sm">
                    </div>
                    <div>
                        <label class="wt_block wt_text-sm wt_font-medium wt_mb-2" for="contact-last-name">
                            <?php esc_html_e('Last Name', 'wicket-acc'); ?> *
                        </label>
                        <input type="text" id="contact-last-name" name="last_name" required
                            class="wt_w-full wt_border wt_border-color wt_rounded-md wt_p-2 wt_text-sm">
                    </div>
                </div>

                <div class="wt_mb-4">
                    <label class="wt_block wt_text-sm wt_font-medium wt_mb-2" for="contact-email">
                        <?php esc_html_e('Email Address', 'wicket-acc'); ?> *
                    </label>
                    <input type="email" id="contact-email" name="email" required
                        class="wt_w-full wt_border wt_border-color wt_rounded-md wt_p-2 wt_text-sm">
                </div>

                <div class="wt_mb-4">
                    <label class="wt_block wt_text-sm wt_font-medium wt_mb-2" for="contact-relationship-type">
                        <?php esc_html_e('Relationship Type', 'wicket-acc'); ?> *
                    </label>
                    <select id="contact-relationship-type" name="relationship_type" required
                        class="wt_w-full wt_border wt_border-color wt_rounded-md wt_p-2 wt_text-sm">
                        <option value=""><?php esc_html_e('Select a relationship type', 'wicket-acc'); ?></option>
                        <?php foreach ($type_labels as $slug => $label): ?>
                            <option value="<?php echo esc_attr($slug); ?>">
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if (!empty($assign_roles)): ?>
                <div class="wt_mb-6">
                    <p class="wt_font-bold wt_mb-3"><?php esc_html_e('Permissions', 'wicket-acc'); ?></p>
                    <div class="wt_space-y-2">
                        <?php foreach ($permission_labels as $slug => $label): ?>
                            <?php if (!in_array($slug, $assign_roles, true)) {
                                continue;
                            } ?>
                            <div class="wt_flex wt_items-center wt_gap-2">
                                <label class="wt_flex wt_items-center wt_gap-2 wt_cursor-pointer">
                                    <input type="checkbox" name="roles[]" value="<?php echo esc_attr($slug); ?>"
                                        class="form-checkbox wt_h-4 wt_w-4 wt_text-bg-interactive wt_rounded">
                                    <span class="wt_text-sm wt_text-content"><?php echo esc_html($label); ?></span>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="wt_flex wt_justify-end wt_gap-3" data-show="!$addContactSuccess">
                    <button type="button"
                        data-on:click="$addContactModalOpen = false;"
                        class="button button--secondary wt_px-4 wt_py-2 wt_text-sm component-button">
                        <?php esc_html_e('Cancel', 'wicket-acc'); ?>
                    </button>
                    <button type="submit"
                        class="button button--primary wt_inline-flex wt_items-center wt_gap-2 wt_px-4 wt_py-2 wt_text-sm component-button"
                        data-class="{ 'wt_pointer-events-none': $addContactSubmitting, 'wt_opacity-50': $addContactSubmitting, 'wt_is-loading': $addContactSubmitting }"
                        data-attr:aria-disabled="$addContactSubmitting ? 'true' : 'false'">
                        <span class="wt_submit_label"><?php esc_html_e('Add Contact', 'wicket-acc'); ?></span>
                        <span class="wt_loader wt_loader_button wt_submit_loader" aria-hidden="true"></span>
                    </button>
                </div>
            </form>

            <div class="wt_flex wt_justify-end wt_pt-4" data-show="$addContactSuccess">
                <button type="button"
                    class="button button--primary wt_px-4 wt_py-2 wt_text-sm component-button"
                    data-on:click="$addContactModalOpen = false;">
                    <?php esc_html_e('Close', 'wicket-acc'); ?>
                </button>
            </div>
        </div>
    </dialog>
</div>
<?php endif; ?>

<!-- Remove Contact Modal -->
<?php if ($show_remove_button): ?>
<div>
    <dialog id="removeContactModal" class="modal wt_m-auto max_wt_md wt_rounded-md wt_shadow-md backdrop_wt_bg-black-50"
        data-show="$removeContactModalOpen"
        data-effect="if ($removeContactModalOpen) el.showModal(); else el.close();"
        data-on:close="<?php echo esc_attr($remove_contact_reset_actions); ?>">
        <div class="wt_bg-white wt_p-6 wt_relative">
            <button type="button" class="orgman-modal__close wt_absolute wt_right-4 wt_top-4 wt_text-lg wt_font-semibold"
                data-on:click="<?php echo esc_attr($remove_contact_reset_actions); ?>" data-show="!$removeContactSuccess">
                x
            </button>
            <h2 class="wp-block-heading has-heading-sm-font-size wt_text-2xl wt_font-semibold wt_mb-4">
                <?php esc_html_e('Remove Contact', 'wicket-acc'); ?>
            </h2>
            <div id="remove-contact-messages">
                <!-- Messages inserted here by Datastar -->
            </div>

            <div data-show="!$removeContactSuccess">
                <p class="wt_mb-6">
                    <?php esc_html_e('Are you sure you want to remove', 'wicket-acc'); ?>
                    <strong data-text="$currentRemoveContactName"></strong>
                    <?php esc_html_e('from the contact list?', 'wicket-acc'); ?>
                    <br>
                    <?php esc_html_e('This will end their relationship with the organization. This action cannot be undone.', 'wicket-acc'); ?>
                </p>

                <form method="POST"
                    data-on:submit="if(!$removeContactSubmitting){ $removeContactSubmitting = true; $contactsLoading = true; @post('<?php echo esc_js($remove_contact_endpoint); ?>', { contentType: 'form' }); }"
                    data-on:submit__prevent-default="true"
                    data-on:success="<?php echo esc_attr($remove_contact_success_actions); ?>"
                    data-on:error="<?php echo esc_attr($remove_contact_error_actions); ?>"
                    data-on:reset="$removeContactSubmitting = false; $contactsLoading = false">

                    <input type="hidden" name="org_uuid" value="<?php echo esc_attr($org_uuid); ?>">
                    <input type="hidden" name="person_uuid" data-attr:value="$currentRemoveContactUuid">
                    <input type="hidden" name="person_name" data-attr:value="$currentRemoveContactName">
                    <input type="hidden" name="connection_ids" data-attr:value="$currentRemoveConnectionIds">
                    <input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce('wicket-orgman-remove-contact')); ?>">

                    <div class="wt_flex wt_justify-end wt_gap-3">
                        <button type="button"
                            data-on:click="<?php echo esc_attr($remove_contact_reset_actions); ?>"
                            class="button button--secondary wt_px-4 wt_py-2 wt_text-sm component-button">
                            <?php esc_html_e('Cancel', 'wicket-acc'); ?>
                        </button>
                        <button type="submit"
                            class="button button--danger wt_inline-flex wt_items-center wt_gap-2 wt_px-4 wt_py-2 wt_text-sm component-button"
                            data-class="{ 'wt_pointer-events-none': $removeContactSubmitting, 'wt_opacity-50': $removeContactSubmitting, 'wt_is-loading': $removeContactSubmitting }"
                            data-attr:aria-disabled="$removeContactSubmitting ? 'true' : 'false'">
                            <span class="wt_submit_label" data-show="!$removeContactSubmitting">
                                <?php esc_html_e('Remove Contact', 'wicket-acc'); ?>
                            </span>
                            <span class="wt_loader wt_loader_button wt_submit_loader" data-show="$removeContactSubmitting" aria-hidden="true"></span>
                        </button>
                    </div>
                </form>
            </div>

            <div class="wt_pt-4" data-show="$removeContactSuccess">
                <div class="wt_flex wt_justify-end">
                    <button type="button"
                        class="button button--primary wt_px-4 wt_py-2 wt_text-sm component-button"
                        data-on:click="<?php echo esc_attr($remove_contact_reset_actions); ?>">
                        <?php esc_html_e('Close', 'wicket-acc'); ?>
                    </button>
                </div>
            </div>
        </div>
    </dialog>
</div>
<?php endif; ?>
