<?php
/**
 * Group profile editing partial.
 */
if (!defined('ABSPATH')) {
    exit;
}

$group_uuid = isset($_GET['group_uuid']) ? sanitize_text_field((string) $_GET['group_uuid']) : '';
$org_uuid = isset($_GET['org_uuid']) ? sanitize_text_field((string) $_GET['org_uuid']) : '';

if (empty($group_uuid)) {
    echo '<p class="wt_text-gray-500">' . esc_html__('No group selected.', 'wicket-acc') . '</p>';

    return;
}

$orgman_config = \WicketORM\Services\ConfigService::getConfig();
$groups_config = is_array($orgman_config['groups'] ?? null) ? $orgman_config['groups'] : [];
$presentation_config = is_array($groups_config['presentation'] ?? null) ? $groups_config['presentation'] : [];
$editable_fields = is_array($presentation_config['editable_fields'] ?? null) ? $presentation_config['editable_fields'] : [];
$enable_edit = (bool) ($presentation_config['enable_group_profile_edit'] ?? true);

$group = function_exists('wicket_get_group') ? wicket_get_group($group_uuid) : null;
$group_attrs = is_array($group) ? ($group['data']['attributes'] ?? []) : [];
$org_uuid = $org_uuid ?: ($group['data']['relationships']['organization']['data']['id'] ?? '');

$lang = function_exists('wicket_get_current_language') ? wicket_get_current_language() : 'en';
$name_key = 'name_' . $lang;
$desc_key = 'description_' . $lang;

$group_name = $group_attrs[$name_key] ?? $group_attrs['name'] ?? '';
$group_description = $group_attrs[$desc_key] ?? $group_attrs['description'] ?? '';

$update_endpoint = WicketORM\Helpers\TemplateHelper::template_url() . 'process/update-group';
?>
<div class="wt_rounded-card wt_bg-light-neutral wt_p-6">
    <h3 class="wt_text-lg wt_font-semibold wt_mb-4"><?php esc_html_e('Group Information', 'wicket-acc'); ?></h3>

    <?php if (!$enable_edit) : ?>
        <p class="wt_text-sm wt_text-content">
            <?php echo esc_html($group_description ?: __('No description available.', 'wicket-acc')); ?>
        </p>
    <?php else : ?>
        <div id="group-update-messages" class="wt_mb-3"></div>
        <form
            method="post"
            data-on:submit="@post('<?php echo esc_js($update_endpoint); ?>', { contentType: 'form' })"
            data-on:success="console.log('Group updated');"
            class="wt_flex wt_flex-col wt_gap-4">
            <input type="hidden" name="group_uuid" value="<?php echo esc_attr($group_uuid); ?>">
            <input type="hidden" name="org_uuid" value="<?php echo esc_attr($org_uuid); ?>">
            <input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce('wicket-orgman-update-group')); ?>">

            <?php if (in_array('name', $editable_fields, true)) : ?>
                <div>
                    <label class="wt_block wt_text-sm wt_font-medium wt_mb-2" for="group-name"><?php esc_html_e('Group Name', 'wicket-acc'); ?></label>
                    <input id="group-name" name="group_name" type="text" class="wt_w-full wt_rounded-md wt_border wt_border-color wt_bg-white wt_p-2" value="<?php echo esc_attr($group_name); ?>" required>
                </div>
            <?php endif; ?>

            <?php if (in_array('description', $editable_fields, true)) : ?>
                <div>
                    <label class="wt_block wt_text-sm wt_font-medium wt_mb-2" for="group-description"><?php esc_html_e('Description', 'wicket-acc'); ?></label>
                    <textarea id="group-description" name="group_description" rows="4" class="wt_w-full wt_rounded-md wt_border wt_border-color wt_bg-white wt_p-2"><?php echo esc_textarea($group_description); ?></textarea>
                </div>
            <?php endif; ?>

            <div class="wt_flex wt_justify-end">
                <button type="submit" class="button button--primary component-button"><?php esc_html_e('Save Group', 'wicket-acc'); ?></button>
            </div>
        </form>
    <?php endif; ?>
</div>
