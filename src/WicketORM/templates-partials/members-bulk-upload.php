<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$org_uuid = isset($org_uuid) ? (string) $org_uuid : '';
$group_uuid = isset($group_uuid) ? (string) $group_uuid : '';
$membership_uuid = isset($membership_uuid) ? (string) $membership_uuid : '';
$bulk_upload_endpoint = isset($bulk_upload_endpoint)
    ? (string) $bulk_upload_endpoint
    : \WicketORM\Helpers\template_url() . 'process/bulk-upload-members';
$bulk_upload_dom_suffix_raw = $org_uuid !== '' ? $org_uuid : ($group_uuid !== '' ? $group_uuid : 'default');
$bulk_upload_dom_suffix = sanitize_html_class($bulk_upload_dom_suffix_raw);
$bulk_upload_messages_id = isset($bulk_upload_messages_id)
    ? (string) $bulk_upload_messages_id
    : 'bulk-upload-messages-' . $bulk_upload_dom_suffix;
$bulk_upload_wrapper_class = isset($bulk_upload_wrapper_class)
    ? (string) $bulk_upload_wrapper_class
    : 'wt_mt-6 wt_rounded-md wt_border wt_border-color wt_bg-white wt_p-4';

// Roster CSV template now lives under the plugin's assets/templates/ directory
// (relocated from src/WicketORM/public/templates/). Resolve via the plugin URL
// constant that the rest of the plugin uses.
$plugin_url = defined('WICKET_ACC_URL') ? WICKET_ACC_URL : plugin_dir_url(dirname(__DIR__, 2) . '/class-wicket-acc-main.php');
$csv_template_url = $plugin_url . 'assets/templates/orm-roster-template.csv';
$orgman_config = WicketORM\Services\ConfigService::getConfig();
$bulk_upload_config = is_array($orgman_config['member_management']['bulk_upload'] ?? null)
    ? $orgman_config['member_management']['bulk_upload']
    : [];
$bulk_columns_config = is_array($bulk_upload_config['columns'] ?? null)
    ? $bulk_upload_config['columns']
    : [];
$default_bulk_columns = [
    'first_name' => ['enabled' => true, 'header' => __('First Name', 'wicket-acc')],
    'last_name' => ['enabled' => true, 'header' => __('Last Name', 'wicket-acc')],
    'email' => ['enabled' => true, 'header' => __('Email Address', 'wicket-acc')],
    'relationship_type' => ['enabled' => true, 'header' => __('Relationship Type', 'wicket-acc')],
    'roles' => ['enabled' => true, 'header' => __('Roles', 'wicket-acc')],
];
$expected_columns = [];
foreach ($default_bulk_columns as $column_key => $defaults) {
    $column_config = is_array($bulk_columns_config[$column_key] ?? null)
        ? $bulk_columns_config[$column_key]
        : [];
    $enabled = (bool) ($column_config['enabled'] ?? $defaults['enabled']);
    if (in_array($column_key, ['first_name', 'last_name', 'email'], true)) {
        $enabled = true;
    }
    if (!$enabled) {
        continue;
    }

    $expected_columns[] = (string) ($column_config['header'] ?? $defaults['header']);
}
$expected_columns_text = implode(', ', $expected_columns);
?>

<div class="orgman-bulk-upload <?php echo esc_attr($bulk_upload_wrapper_class); ?>">
    <h3 class="wt_text-base wt_font-semibold wt_mb-2"><?php esc_html_e('Bulk Upload Members', 'wicket-acc'); ?></h3>
    <p class="wt_text-sm wt_text-content wt_mb-3">
        <?php esc_html_e('Upload a CSV file to add multiple members at once. Existing active members are skipped automatically.', 'wicket-acc'); ?>
    </p>

    <div id="<?php echo esc_attr($bulk_upload_messages_id); ?>" class="wt_mb-3"></div>

    <div class="wt_mb-3">
        <a class="orgman-bulk-upload__template-link wt_text-sm"
            href="<?php echo esc_url($csv_template_url); ?>"
            download="roster_template.csv">
            <?php esc_html_e('Download CSV Template', 'wicket-acc'); ?>
        </a>
    </div>

    <form
        method="POST"
        enctype="multipart/form-data"
        data-on:submit="if(!$bulkUploadSubmitting){ $bulkUploadSubmitting = true; $membersLoading = true; @post('<?php echo esc_js($bulk_upload_endpoint); ?>', { contentType: 'form' }); }"
        data-on:submit__prevent-default="true"
        data-on:error="$bulkUploadSubmitting = false; $membersLoading = false">
        <input type="hidden" name="org_uuid" value="<?php echo esc_attr($org_uuid); ?>">
        <input type="hidden" name="membership_uuid" value="<?php echo esc_attr($membership_uuid); ?>">
        <input type="hidden" name="group_uuid" value="<?php echo esc_attr($group_uuid); ?>">
        <input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce('wicket-orgman-bulk-upload-members')); ?>">

        <label class="wt_block wt_text-sm wt_font-medium wt_mb-2" for="bulk-upload-file-<?php echo esc_attr($bulk_upload_dom_suffix); ?>">
            <?php esc_html_e('CSV File', 'wicket-acc'); ?>
        </label>
        <input
            id="bulk-upload-file-<?php echo esc_attr($bulk_upload_dom_suffix); ?>"
            type="file"
            name="bulk_file"
            accept=".csv,text/csv"
            required
            class="orgman-bulk-upload__file-input wt_block wt_w-full wt_text-sm wt_mb-3">

        <p class="wt_text-sm wt_text-content wt_mb-3">
            <?php
            echo esc_html(
                sprintf(
                    /* translators: %s list of configured CSV columns */
                    __('Expected columns: %s', 'wicket-acc'),
                    $expected_columns_text
                )
            );
?>
        </p>
        <p class="wt_text-sm wt_text-content wt_mb-3">
            <?php esc_html_e('For multiple roles, separate values with "|" only.', 'wicket-acc'); ?>
        </p>

        <div class="wt_flex wt_justify-end">
            <button
                type="submit"
                class="button button--primary wt_button_submit_async wt_inline-flex wt_items-center wt_gap-2 component-button"
                data-class="{ 'wt_pointer-events-none': $bulkUploadSubmitting, 'wt_opacity-50': $bulkUploadSubmitting, 'wt_is-loading': $bulkUploadSubmitting }"
                data-attr:aria-disabled="$bulkUploadSubmitting ? 'true' : 'false'">
                <span class="wt_submit_label" data-show="!$bulkUploadSubmitting">
                    <?php esc_html_e('Upload and Add Members', 'wicket-acc'); ?>
                </span>
                <span class="wt_loader wt_loader_button wt_submit_loader"
                    data-show="$bulkUploadSubmitting"
                    aria-hidden="true"></span>
            </button>
        </div>
    </form>
</div>
