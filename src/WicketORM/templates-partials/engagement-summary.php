<?php

/**
 * Engagement / donation summary partial.
 *
 * Renders configurable sections (e.g. Foundation, PAC) with field values and badges.
 * Loaded via EngagementController's REST endpoint; embed with Datastar data-on-load:
 *
 *   <div
 *     data-on-load="@get('<?php echo esc_url(rest_url('wicket/orm/v1/engagement/person')); ?>?org_id=...')"
 *   ></div>
 *
 * Variables provided by EngagementController::htmlResponse():
 *   $engagement (array|null)  – result of EngagementService::getPersonEngagement()
 *   $notice     (array|null)  – ['type' => 'error|success', 'message' => '...']
 */
if (!defined('ABSPATH')) {
    exit;
}

$engagement = isset($engagement) && is_array($engagement) ? $engagement : null;
$notice = isset($notice) && is_array($notice) ? $notice : null;
?>

<div class="wt_engagement-summary">

    <?php if ($notice !== null) : ?>
        <?php $notice_type = (string) ($notice['type'] ?? 'error'); ?>
        <?php if ($notice_type === 'error') : ?>
            <?php
            get_component('alert', [
                'classes' => ['wt_bg-red-100', 'wt_border', 'wt_border-red-400', 'wt_text-red-700', 'wt_px-4', 'wt_py-3', 'wt_rounded-sm', 'wt_mb-4'],
                'content' => esc_html((string) ($notice['message'] ?? '')),
            ]);
            ?>
        <?php else : ?>
            <?php
            get_component('alert', [
                'classes' => ['wt_bg-green-100', 'wt_border', 'wt_border-green-400', 'wt_text-green-700', 'wt_px-4', 'wt_py-3', 'wt_rounded-sm', 'wt_mb-4'],
                'content' => esc_html((string) ($notice['message'] ?? '')),
            ]);
            ?>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($engagement === null) : ?>
        <?php if ($notice === null) : ?>
            <p class="wt_text-gray-500"><?php esc_html_e('No engagement data available.', 'wicket-acc'); ?></p>
        <?php endif; ?>
        <?php return; ?>
    <?php endif; ?>

    <?php
    $sections = is_array($engagement['sections'] ?? null) ? $engagement['sections'] : [];
$badges = is_array($engagement['badges'] ?? null) ? $engagement['badges'] : [];
$is_active_member = (bool) ($engagement['is_active_member'] ?? false);
?>

    <?php foreach ($sections as $section_slug => $section) : ?>
        <?php
    $section_label = (string) ($section['label'] ?? $section_slug);
        $section_fields = is_array($section['fields'] ?? null) ? $section['fields'] : [];
        $section_badges = is_array($badges[$section_slug] ?? null) ? $badges[$section_slug] : [];
        ?>

        <div class="wt_engagement-section wt_mb-6" data-section="<?php echo esc_attr($section_slug); ?>">

            <h3 class="wt_engagement-section__title wt_text-lg wt_font-semibold wt_mb-3">
                <?php echo esc_html($section_label); ?>
            </h3>

            <?php if (!empty($section_badges)) : ?>
                <div class="wt_engagement-badges wt_mb-3">
                    <?php foreach ($section_badges as $badge) : ?>
                        <span class="wt_badge wt_badge--primary">
                            <?php echo esc_html((string) $badge); ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($section_fields)) : ?>
                <dl class="wt_engagement-fields">
                    <?php foreach ($section_fields as $field_key => $field) : ?>
                        <div class="wt_engagement-field wt_flex wt_justify-between wt_py-1 wt_border-b">
                            <dt class="wt_engagement-field__label wt_text-sm wt_text-gray-600">
                                <?php echo esc_html((string) ($field['label'] ?? $field_key)); ?>
                            </dt>
                            <dd class="wt_engagement-field__value wt_text-sm wt_font-medium">
                                <?php echo esc_html((string) ($field['value'] ?? '')); ?>
                            </dd>
                        </div>
                    <?php endforeach; ?>
                </dl>
            <?php endif; ?>

        </div>

    <?php endforeach; ?>

    <?php if (empty($sections)) : ?>
        <p class="wt_text-gray-500"><?php esc_html_e('No engagement data available for this period.', 'wicket-acc'); ?></p>
    <?php endif; ?>

</div>
