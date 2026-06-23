<?php

/**
 * Export members modal trigger and dialog.
 *
 * Include this partial on any org-management page where exports are enabled.
 * Required variables:
 *   $org_uuid        (string)
 *   $membership_uuid (string)
 *   $org_dom_suffix  (string) – DOM-safe identifier, e.g. sanitize_html_class($org_uuid)
 *
 * Optional:
 *   $recipient_email (string) – pre-fill; defaults to current user's email
 */
if (!defined('ABSPATH')) {
    exit;
}

$config = WicketORM\Services\ConfigService::getConfig();
$export_config = is_array($config['exports'] ?? null) ? $config['exports'] : [];

if (empty($export_config['enabled'])) {
    return;
}

$org_uuid = isset($org_uuid) ? sanitize_text_field((string) $org_uuid) : '';
$membership_uuid = isset($membership_uuid) ? sanitize_text_field((string) $membership_uuid) : '';
$org_dom_suffix = isset($org_dom_suffix) ? sanitize_html_class((string) $org_dom_suffix) : sanitize_html_class($org_uuid ?: 'default');
$current_user = wp_get_current_user();
$recipient_email = isset($recipient_email) ? sanitize_email((string) $recipient_email) : sanitize_email($current_user->user_email ?? '');
$nonce = wp_create_nonce('wicket_orgman_export_' . $org_uuid);

$export_url = rest_url('org-management/v1/exports/initiate');
$dialog_id = 'exportMembersDialog-' . $org_dom_suffix;
$messages_id = 'export-messages-' . $org_dom_suffix;
?>

<div data-signals="{exportSubmitting: false, exportQueued: false}">

    <button
        type="button"
        class="wt_button wt_button--secondary"
        onclick="document.getElementById('<?php echo esc_attr($dialog_id); ?>').showModal()"
    >
        <?php esc_html_e('Export Members', 'wicket-acc'); ?>
    </button>

    <dialog
        id="<?php echo esc_attr($dialog_id); ?>"
        class="wt_dialog"
    >
        <div class="wt_dialog__header">
            <h2 class="wt_dialog__title"><?php esc_html_e('Export Members', 'wicket-acc'); ?></h2>
            <button
                type="button"
                class="wt_dialog__close"
                onclick="document.getElementById('<?php echo esc_attr($dialog_id); ?>').close()"
                aria-label="<?php esc_attr_e('Close', 'wicket-acc'); ?>"
            >&times;</button>
        </div>

        <div class="wt_dialog__body">

            <div id="<?php echo esc_attr($messages_id); ?>"></div>

            <div data-show="!exportQueued">
                <p><?php esc_html_e('The member list will be exported as a CSV file. When the export is ready, a download link will be sent to the email address below.', 'wicket-acc'); ?></p>

                <form
                    data-on-submit="
                        $exportSubmitting = true;
                        evt.preventDefault();
                        @post('<?php echo esc_url($export_url); ?>', {
                            headers: {'X-WP-Nonce': '<?php echo esc_js(wp_create_nonce('wp_rest')); ?>'},
                            body: new FormData(evt.target)
                        });
                    "
                >
                    <input type="hidden" name="org_id" value="<?php echo esc_attr($org_uuid); ?>">
                    <input type="hidden" name="membership_uuid" value="<?php echo esc_attr($membership_uuid); ?>">
                    <input type="hidden" name="org_dom_suffix" value="<?php echo esc_attr($org_dom_suffix); ?>">
                    <input type="hidden" name="_wpnonce" value="<?php echo esc_attr($nonce); ?>">

                    <div class="wt_form-group">
                        <label for="export-email-<?php echo esc_attr($org_dom_suffix); ?>" class="wt_label">
                            <?php esc_html_e('Send download link to', 'wicket-acc'); ?>
                        </label>
                        <input
                            type="email"
                            id="export-email-<?php echo esc_attr($org_dom_suffix); ?>"
                            name="recipient_email"
                            value="<?php echo esc_attr($recipient_email); ?>"
                            class="wt_input"
                            required
                        >
                    </div>

                    <div class="wt_dialog__footer">
                        <button
                            type="button"
                            class="wt_button wt_button--ghost"
                            onclick="document.getElementById('<?php echo esc_attr($dialog_id); ?>').close()"
                        >
                            <?php esc_html_e('Cancel', 'wicket-acc'); ?>
                        </button>
                        <button
                            type="submit"
                            class="wt_button wt_button--primary"
                            data-bind-disabled="exportSubmitting"
                        >
                            <span data-show="!exportSubmitting"><?php esc_html_e('Export', 'wicket-acc'); ?></span>
                            <span data-show="exportSubmitting"><?php esc_html_e('Processing…', 'wicket-acc'); ?></span>
                        </button>
                    </div>
                </form>
            </div>

            <div data-show="exportQueued" class="wt_text-center wt_py-4">
                <p><?php esc_html_e('Your export has been queued. You will receive an email when the download is ready.', 'wicket-acc'); ?></p>
                <button
                    type="button"
                    class="wt_button wt_button--secondary"
                    onclick="document.getElementById('<?php echo esc_attr($dialog_id); ?>').close(); $exportQueued = false;"
                >
                    <?php esc_html_e('Close', 'wicket-acc'); ?>
                </button>
            </div>

        </div>
    </dialog>

</div>
