<div class="wt_w-full wt_rounded-card-accent wt_p-4 wt_mb-4 wt_hover_shadow-sm wt_transition-shadow wt_bg-card wt_border wt_border-color"
    role="listitem">
    <?php
    $org_uuid_param = isset($org_uuid_for_links) ? (string) $org_uuid_for_links : (string) $org_id;
    $title_url_base = WicketORM\Helpers\Helper::getMyAccountPageUrl('organization-management', '/my-account/organization-management/');
    $title_params = [];
    if ($org_uuid_param !== '') {
        $title_params['org_uuid'] = $org_uuid_param;
    }
    if ($primary_group_uuid !== '') {
        $title_params['group_uuid'] = $primary_group_uuid;
    }
    $title_url = !empty($title_params) ? add_query_arg($title_params, $title_url_base) : $title_url_base;
    $can_open_org = $can_edit_org || $is_membership_manager;
    ?>
    <h2 class="wp-block-heading has-heading-sm-font-size wt_text-2xl wt_mb-3">
        <?php if ($can_open_org): ?>
            <a href="<?php echo esc_url($title_url); ?>"
                class="wt_text-content wt_hover_text-primary-600 wt_focus_outline-hidden wt_focus_ring-2 wt_focus_ring-primary-500 wt_focus_ring-offset-2 wt_decoration-none">
                <?php echo esc_html($org_name); ?>
            </a>
        <?php else: ?>
            <span class="wt_text-content"><?php echo esc_html($org_name); ?></span>
        <?php endif; ?>
    </h2>
    <div class="wt_flex wt_flex-col">
        <?php foreach ($membership_entries as $entry_index => $membership_entry): ?>
            <?php
            $entry_membership_uuid = (string) ($membership_entry['membership_uuid'] ?? '');
            $entry_membership_name = (string) ($membership_entry['membership_name'] ?? '');
            $entry_is_active = (bool) ($membership_entry['is_active'] ?? false);
            ?>
            <div class="wt_flex wt_flex-col wt_gap-2<?php echo $entry_index > 0 ? ' wt_pt-4 wt_mt-1 wt_border-t wt_border-color' : ''; ?>">
                <div class="wt_flex wt_items-center wt_text-content">
                    <?php if ($entry_membership_name !== ''): ?>
                        <span class="wt_text-base">
                            <?php
                            printf(
                                /* translators: %s: Membership tier name. */
                                esc_html__('Membership Tier: %s', 'wicket-acc'),
                                esc_html($entry_membership_name)
                            );
                        ?>
                        </span>
                    <?php elseif ($entry_membership_uuid !== ''): ?>
                        <span class="wt_text-base"><?php esc_html_e('Active Membership', 'wicket-acc'); ?></span>
                    <?php else: ?>
                        <span class="wt_text-base"><?php esc_html_e('No membership found', 'wicket-acc'); ?></span>
                    <?php endif; ?>
                </div>

                <?php if ($entry_is_active): ?>
                    <div class="wt_flex wt_items-center wt_gap-2">
                        <span class="wt_inline-block wt_w-2 wt_h-2 wt_rounded-full wt_bg-green-500" aria-hidden="true"></span><span class="wt_text-base wt_leading-none wt_text-content"><?php esc_html_e('Active Member', 'wicket-acc'); ?></span>
                    </div>
                <?php elseif ($entry_membership_uuid !== ''): ?>
                    <div class="wt_flex wt_items-center wt_gap-2">
                        <span class="wt_inline-block wt_w-2 wt_h-2 wt_rounded-full wt_bg-gray-400" aria-hidden="true"></span><span class="wt_text-base wt_leading-none wt_text-content"><?php esc_html_e('Inactive Membership', 'wicket-acc'); ?></span>
                    </div>
                <?php endif; ?>

                <?php
                $card_org_list_config = WicketORM\Services\ConfigService::getConfig()['presentation']['organization_list'] ?? [];
            $card_show_my_role = (bool) ($card_org_list_config['show_my_role'] ?? true);
            ?>
                <?php if ($card_show_my_role) : ?>
                <div class="wt_text-base wt_font-bold wt_text-content">
                    <span><?php esc_html_e('My Role(s):', 'wicket-acc'); ?></span>
                    <?php if (!empty($formatted_roles)): ?>
                        <?php echo esc_html(implode(', ', $formatted_roles)); ?>
                    <?php else: ?>
                        <?php esc_html_e('No roles assigned', 'wicket-acc'); ?>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($group_details)): ?>
                    <?php
                $group_types = [];
                    $group_tags = [];
                    foreach ($group_details as $group_detail) {
                        $type = (string) ($group_detail['type'] ?? '');
                        if ($type !== '') {
                            $group_types[] = ucwords(str_replace('_', ' ', $type));
                        }

                        $tags = $group_detail['tags'] ?? null;
                        if (is_array($tags)) {
                            foreach ($tags as $tag) {
                                $tag = is_string($tag) ? trim($tag) : '';
                                if ($tag !== '') {
                                    $group_tags[] = $tag;
                                }
                            }
                        }
                    }
                    $group_types = array_values(array_unique($group_types));
                    $group_tags = array_values(array_unique($group_tags));
                    ?>
                    <?php if (!empty($group_types)): ?>
                        <div class="wt_text-base wt_text-content">
                            <span class="wt_font-semibold"><?php esc_html_e('Type:', 'wicket-acc'); ?></span>
                            <?php echo esc_html(implode(', ', $group_types)); ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($group_tags)): ?>
                        <div class="wt_text-base wt_text-content">
                            <span class="wt_font-semibold"><?php esc_html_e('Tag(s):', 'wicket-acc'); ?></span>
                            <?php echo esc_html(implode(', ', $group_tags)); ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <div class="wt_flex wt_items-stretch wt_gap-4 wt_mt-1">
                    <?php if ($can_edit_org): ?>
                        <?php
                        $profile_url_base = WicketORM\Helpers\Helper::getMyAccountPageUrl('organization-profile', '/my-account/organization-profile/');
                        $profile_params = [];
                        if ($org_uuid_param !== '') {
                            $profile_params['org_uuid'] = $org_uuid_param;
                        }
                        if ($roster_mode === 'membership_cycle' && $entry_membership_uuid !== '') {
                            $profile_params['membership_uuid'] = $entry_membership_uuid;
                        }
                        if ($roster_mode === 'groups' && $primary_group_uuid !== '') {
                            $profile_params['group_uuid'] = $primary_group_uuid;
                        }
                        ?>
                        <a href="<?php echo esc_url(add_query_arg($profile_params, $profile_url_base)); ?>"
                            class="wt_inline-flex wt_items-center wt_text-primary-600 wt_hover_text-primary-700 underline underline-offset-4">
                            <?php esc_html_e('Edit Organization', 'wicket-acc'); ?>
                        </a>
                    <?php endif; ?>

                    <?php if ($has_any_roles): ?>
                        <span class="wt_px-2 wt_h-4 wt_bg-border-white" aria-hidden="true"></span>
                    <?php endif; ?>

                    <?php if ($is_membership_manager): ?>
                        <?php
                        $members_url_base = WicketORM\Helpers\Helper::getMyAccountPageUrl('organization-members', '/my-account/organization-members/');
                        $members_params = [];
                        if ($org_uuid_param !== '') {
                            $members_params['org_uuid'] = $org_uuid_param;
                        }
                        if ($roster_mode === 'membership_cycle' && $entry_membership_uuid !== '') {
                            $members_params['membership_uuid'] = $entry_membership_uuid;
                        }
                        if ($primary_group_uuid !== '') {
                            $members_params['group_uuid'] = $primary_group_uuid;
                        }
                        ?>
                        <a href="<?php echo esc_url(add_query_arg($members_params, $members_url_base)); ?>"
                            class="wt_inline-flex wt_items-center wt_text-primary-600 wt_hover_text-primary-700 underline underline-offset-4">
                            <?php esc_html_e('Manage Members', 'wicket-acc'); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
