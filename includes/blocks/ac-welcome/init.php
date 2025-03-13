<?php

namespace WicketAcc\Blocks\Welcome;

use WicketAcc\Blocks;

// No direct access
defined('ABSPATH') || exit();

/**
 * Wicket Welcome Block.
 **/
class init extends Blocks
{
    /**
     * Constructor.
     */
    public function __construct(
        protected array $block = [],
        protected bool $is_preview = false
    ) {
        $this->block = $block;
        $this->is_preview = $is_preview;

        // Display the block
        $this->init_block();
    }

    /**
     * Init block.
     *
     * @return void
     */
    protected function init_block()
    {
        $iso_code = apply_filters('wpml_current_language', null);

        if (empty($iso_code)) {
            $locale = get_locale(); // Get the full locale (e.g., en_US)
            $iso_code = substr($locale, 0, 2); // Extract the first two characters
        }

        $current_user = wp_get_current_user();
        $person = wicket_current_person();
        $identifying_number = $person->identifying_number;
        $membership_began_on = $person->membership_began_on;
        $edit_profile = get_field('edit_profile_button');
        $edit_profile_button_link = get_field('edit_profile_button_link');
        $member_since = get_field('member_since');
        $renewal_date = get_field('renewal_date');
        $display_mdp_id = get_field('display_mdp_id');
        $image_url = get_avatar_url($current_user->ID, ['size' => '300']);
        $active_memberships = wicket_get_active_memberships($iso_code);

        // Edit profile button (link and title)
        if (
            empty($edit_profile_button_link) ||
            !is_array($edit_profile_button_link)
        ) {
            // Use ACC mapping
            $acc_editprofile_page = get_field(
                'acc_page_edit-profile',
                'option'
            );
            $editprofile_page_link = get_permalink($acc_editprofile_page);
            $editprofile_page_title = get_the_title($acc_editprofile_page);
        } else {
            // Use user defined URL
            $editprofile_page_link = $edit_profile_button_link['url'];
            $editprofile_page_title = $edit_profile_button_link['title'];
        }
        ?>
        <div class="wicket-acc-block wicket-acc-block-welcome wp-block-wicket-acc-callout row <?php echo defined('WICKET_WP_THEME_V2') ? 'wicket-acc-block-welcome--v2' : 'bg-light-010'; ?>">
            <div class="wicket-welcome-avatar col-2">
                <?php if ($image_url) {
                    echo '<img src="' .
                        $image_url .
                        '" alt="' .
                        $person->given_name .
                        ' ' .
                        $person->family_name .
                        __(' Profile Image', 'wicket-acc') .
                        '" />';
                } ?>
            </div>

            <div class="wicket-welcome-content-container col row">
                <div class="wicket-welcome-content col">
                    <p class="wicket-welcome-label">
                        <?php _e('Welcome', 'wicket-acc'); ?>
                    </p>
                    <p class="wicket-welcome-name">
                        <?php $member_name = $person->given_name . ' ' . $person->family_name; ?>
                        <?php echo apply_filters('wicket/acc/block/welcome_block_name', $member_name, $person); ?>
                        <?php do_action('wicket/acc/block/after_welcome_block_name', $person->id); ?>
                    </p>

                    <?php if ($active_memberships) {
                        // Track seen membership combinations to avoid duplicates
                        $seen_memberships = [];
                        foreach ($active_memberships as $membership) {

                            if (function_exists('wicket_acc_welcome_filter_memberships')) {
                                if (wicket_acc_welcome_filter_memberships($membership)) {
                                    continue;
                                }
                            }

                            // Create a unique key based on membership name and organization (if present)
                            $membership_key = $membership['name'];
                            if ($membership['type'] == 'organization') {
                                $org_main_info = WACC()->MdpApi->get_organization_membership_by_uuid(
                                    $membership['organization_membership_id']
                                );
                                $org_uuid =
                                    $org_main_info['data']['relationships']['organization']['data']['id'];
                                $org_info = wicket_get_active_memberships_relationship(
                                    $org_uuid
                                );
                                $membership_key .= '-' . $org_info['name'];
                            }

                            // Skip if we've seen this combination before
                            if (isset($seen_memberships[$membership_key])) {
                                continue;
                            }
                            $seen_memberships[$membership_key] = true;
                            ?>
                            <div class="my-0 wicket-welcome-memberships">
                                <p class="mb-0 wicket-welcome-member-type">
                                    <strong><?php echo __('Membership Type:', 'wicket-acc'); ?></strong>
                                    <?php echo apply_filters(
                                        'wicket_ac_welcome_block_membership_name',
                                        $membership['name']
                                    ); ?>
                                </p>

                                <?php if ($membership['type'] == 'organization'):

                                    $org_main_info = WACC()->MdpApi->get_organization_membership_by_uuid(
                                        $membership['organization_membership_id']
                                    );
                                    $org_uuid =
                                        $org_main_info['data']['relationships']['organization']['data']['id'];

                                    $org_info = wicket_get_active_memberships_relationship($org_uuid);
                                    ?>
                                    <p class="mb-0 wicket-welcome-member-org font-bold">
                                        <?php echo __($org_info['relationship'], 'wicket-acc'); ?>
                                        &ndash;
                                        <?php echo $org_info['name']; ?>
                                    </p>
                                <?php
                                endif; ?>

                                <p class="mt-0 mb-2 wicket-welcome-member-active flex items-center space-x-2">
                                    <span
                                        class="text-gray-700"><?php echo __('Active Member', 'wicket-acc'); ?></span>
                                </p>

                                <?php if ($display_mdp_id): ?>
                                    <p class="wicket-welcome-member-mdp-id mb-0">
                                        <span><?php echo __('ID:', 'wicket-acc'); ?></span>
                                        <?php echo $identifying_number; ?>
                                    </p>
                                <?php endif; ?>

                                <?php if (
                                    $member_since &&
                                    !empty($membership['starts_at']) &&
                                    strtotime($membership['starts_at'])
                                ): ?>
                                    <p class="wicket-welcome-member-since mb-0">
                                        <?php esc_html_e(__('Member Since:', 'wicket-acc')); ?>
                                        <?php if (isset($membership_began_on) && !empty($membership_began_on)) {
                                            echo date('F j, Y', strtotime($membership_began_on));
                                        } else {
                                            echo date('F j, Y', strtotime($membership['starts_at']));
                                        } ?>
                                    </p>
                                <?php endif; ?>

                                <?php if (
                                    $renewal_date &&
                                    !empty($membership['ends_at']) &&
                                    strtotime($membership['ends_at'])
                                ): ?>
                                    <p class="wicket-welcome-renewal mb-0">
                                        <?php echo __('Renewal Date:', 'wicket-acc'); ?>
                                        <?php echo date('F j, Y', strtotime($membership['ends_at'])); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                    <?php
                        }
                    } ?>
                </div>
                <?php if (
                    $edit_profile &&
                    isset($editprofile_page_link) &&
                    isset($editprofile_page_title)
                ) { ?>
                    <div class="wicket-welcome-edit-profile-button col-3 text-right">
                        <?php get_component('button', [
                            'variant' => 'secondary',
                            'a_tag' => true,
                            'classes' => ['whitespace-nowrap'],
                            'label' => $editprofile_page_title,
                            'prefix_icon' => 'fa-regular fa-pen-to-square',
                            'link' => $editprofile_page_link,
                        ]); ?>
                    </div>
                <?php } ?>
            </div>
        </div>
<?php
    }
}
