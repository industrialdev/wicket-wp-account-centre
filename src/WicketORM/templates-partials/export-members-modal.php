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
// sanitize_html_class keeps hyphens (UUIDs have them). Datastar signal keys are
// JS identifiers, where '-' parses as subtraction. Derive a JS-safe suffix:
// sanitize_key (lowercase alnum + _ + -) then swap dashes for underscores.
// See https://developer.wordpress.org/reference/functions/sanitize_key/
$org_dom_suffix = isset($org_dom_suffix) ? sanitize_html_class((string) $org_dom_suffix) : sanitize_html_class($org_uuid ?: 'default');
$current_user = wp_get_current_user();
$recipient_email = isset($recipient_email) ? sanitize_email((string) $recipient_email) : sanitize_email($current_user->user_email ?? '');
$nonce = wp_create_nonce('wicket_orgman_export_' . $org_uuid);

$export_url = rest_url('wicket/orm/v1/exports/initiate');

// JS-safe suffix: lowercase alnum + underscores only. Used as a Datastar signal
// key suffix (signals must be valid JS identifiers). Multi-tier orgs render
// several cards, so each card needs its own signal namespace.
$safe_suffix = str_replace('-', '_', sanitize_key($org_dom_suffix));
$sig_open = "exportOpen_{$safe_suffix}";
$sig_submitting = "exportSubmitting_{$safe_suffix}";
$sig_queued = "exportQueued_{$safe_suffix}";
$dialog_id = 'exportMembersDialog_' . $safe_suffix;
// HTML element ids may keep hyphens; only Datastar signal names must not.
$messages_id = 'export-messages-' . $org_dom_suffix;

$rest_nonce = wp_create_nonce('wp_rest');

$data_signals = wp_json_encode([
    $sig_open => false,
    $sig_submitting => false,
    $sig_queued => false,
]);
?>

<div data-signals='<?php echo $data_signals; ?>'>

    <button
        type="button"
        class="button button--secondary add-member-button wt_w-full wt_mt-3 wt_py-2 component-button"
        data-on:click="$<?php echo esc_js($sig_open); ?> = true"
    >
        <?php esc_html_e('Download Roster', 'wicket-acc'); ?>
    </button>
    <?php
    // WWID-1907: when the roster exceeds the configured sync_threshold, the
    // export runs asynchronously (WP-Cron batches) and the download link is
    // emailed rather than delivered instantly. Surface that upfront so the
    // user sets the right expectation before clicking.
    $sync_threshold = max(0, (int) ($export_config['sync_threshold'] ?? 250));
    $roster_count = isset($org_roster_count) ? (int) $org_roster_count : 0;
    if ($sync_threshold > 0 && $roster_count > $sync_threshold) :
    ?>
        <p class="wt_mt-2 wt_mb-0 wt_text-xs wt_text-color-500 wt_text-center">
            <?php
            echo esc_html(sprintf(
                /* translators: %d: member count threshold */
                __('This roster is large (%1$d members) and will be prepared in the background. After clicking, a download link will be emailed to you.', 'wicket-acc'),
                $roster_count
            ));
        ?>
        </p>
    <?php endif; ?>

    <dialog
        id="<?php echo esc_attr($dialog_id); ?>"
        class="modal wt_m-auto max_wt_3xl wt_rounded-md wt_shadow-md backdrop_wt_bg-black-50"
        data-show="$<?php echo esc_attr($sig_open); ?>"
        data-effect="if ($<?php echo esc_attr($sig_open); ?>) el.showModal(); else el.close();"
        data-on:close="$<?php echo esc_attr($sig_open); ?> = false; $<?php echo esc_attr($sig_submitting); ?> = false; $<?php echo esc_attr($sig_queued); ?> = false;"
    >
        <div class="wt_bg-white wt_p-6 wt_relative">
            <button
                type="button"
                class="orgman-modal__close wt_absolute wt_right-4 wt_top-4 wt_text-lg wt_font-semibold"
                data-on:click="$<?php echo esc_attr($sig_open); ?> = false"
                aria-label="<?php esc_attr_e('Close', 'wicket-acc'); ?>"
            >x</button>

            <h2 class="wp-block-heading has-heading-sm-font-size wt_text-2xl wt_font-semibold wt_mb-4">
                <?php esc_html_e('Download Roster', 'wicket-acc'); ?>
            </h2>

            <div id="<?php echo esc_attr($messages_id); ?>"></div>

            <div data-show="!$<?php echo esc_attr($sig_queued); ?>">
                <p class="wt_mb-6"><?php esc_html_e('Download the current roster as a CSV file. Small rosters download immediately; large rosters are prepared in the background and a download link is emailed to you.', 'wicket-acc'); ?></p>

                <form
                    data-on:submit="
                        $<?php echo esc_js($sig_submitting); ?> = true;
                        @post('<?php echo esc_js($export_url); ?>', {
                            contentType: 'form',
                            headers: {'X-WP-Nonce': '<?php echo esc_js($rest_nonce); ?>'}
                        });
                    "
                    data-on:submit__prevent-default="true"
                    data-on:success="$<?php echo esc_js($sig_submitting); ?> = false;"
                    data-on:error="console.error('Failed to initiate roster export'); $<?php echo esc_js($sig_submitting); ?> = false;"
                >
                    <input type="hidden" name="org_id" value="<?php echo esc_attr($org_uuid); ?>">
                    <input type="hidden" name="membership_uuid" value="<?php echo esc_attr($membership_uuid); ?>">
                    <input type="hidden" name="org_dom_suffix" value="<?php echo esc_attr($org_dom_suffix); ?>">
                    <input type="hidden" name="export_nonce" value="<?php echo esc_attr($nonce); ?>">
                    <input type="hidden" name="recipient_email" value="<?php echo esc_attr($recipient_email); ?>">

                    <div class="wt_flex wt_justify-end wt_gap-3 wt_pt-4">
                        <button type="button" class="button button--secondary component-button"
                            data-on:click="$<?php echo esc_attr($sig_open); ?> = false"
                            data-class="{ 'wt_pointer-events-none': $<?php echo esc_attr($sig_submitting); ?>, 'wt_opacity-50': $<?php echo esc_attr($sig_submitting); ?> }"
                            data-attr:aria-disabled="$<?php echo esc_attr($sig_submitting); ?> ? 'true' : 'false'"
                            aria-disabled="false">
                            <?php esc_html_e('Cancel', 'wicket-acc'); ?>
                        </button>
                        <button type="submit" class="button button--primary wt_button_submit_async wt_inline-flex wt_items-center wt_gap-2 component-button"
                            data-class="{ 'wt_pointer-events-none': $<?php echo esc_attr($sig_submitting); ?>, 'wt_opacity-50': $<?php echo esc_attr($sig_submitting); ?>, 'wt_is-loading': $<?php echo esc_attr($sig_submitting); ?> }"
                            data-attr:aria-disabled="$<?php echo esc_attr($sig_submitting); ?> ? 'true' : 'false'"
                            aria-disabled="false">
                            <span class="wt_submit_label"><?php esc_html_e('Download', 'wicket-acc'); ?></span>
                            <span class="wt_loader wt_loader_button wt_submit_loader" aria-hidden="true"></span>
                        </button>
                    </div>
                </form>
            </div>

            <div data-show="$<?php echo esc_attr($sig_queued); ?>" class="wt_text-center wt_py-4">
                <p class="wt_mb-6"><?php esc_html_e('Your roster is large and is being prepared in the background. A download link will be emailed to you shortly.', 'wicket-acc'); ?></p>
                <button
                    type="button"
                    class="wt_button wt_button--secondary"
                    data-on:click="$<?php echo esc_attr($sig_open); ?> = false; $<?php echo esc_attr($sig_queued); ?> = false"
                >
                    <?php esc_html_e('Close', 'wicket-acc'); ?>
                </button>
            </div>
        </div>
    </dialog>

</div>
