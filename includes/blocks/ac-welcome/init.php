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
        $current_lang = wicket_get_current_language();
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
        $active_memberships = WACC()->Mdp()->Membership()->getCurrentPersonActiveMemberships($current_lang);

        $renewal_end_timestamp = $renewal_date
            ? WACC()->Mdp()->Membership()->getCurrentPersonRenewalEndTimestamp()
            : null;

        // We need to find these at the MDP at some point
        $relationship_translations = [
            'Primary Contact'             => 'Personne-ressource principale',
            'Voting Contact'              => 'Personne-ressource habilitée à voter',
            'Primary Tradeshow Contact'   => 'Personne-ressource principale pour les salons',
            'Secondary Tradeshow Contact' => 'Personne-ressource secondaire pour les salons',
            'Accounting Contact'          => 'Personne-ressource à la comptabilité',
            'Regulatory'                  => 'Affaires réglementaires',
            'Member'                      => 'Membre',
            'Employee'                    => 'Employé(e)',
        ];

        // Edit profile button (link and title)
        if (
            empty($edit_profile_button_link)
            || !is_array($edit_profile_button_link)
        ) {
            // Use ACC mapping
            $editprofile_page_link = WACC()->get_account_page_url('edit-profile');
            $editprofile_page_title = __('Edit Profile', 'wicket-acc');
        } else {
            // Use user defined URL
            $editprofile_page_link = $edit_profile_button_link['url'];
            $editprofile_page_title = $edit_profile_button_link['title'];
        }
        ?>
        <div class="wicket-acc-block wicket-acc-block-welcome wp-block-wicket-acc-callout row <?php echo defined('WICKET_WP_THEME_V2') ? 'wicket-acc-block-welcome--v2' : 'bg-light-010'; ?>">
            <div class="wicket-welcome-avatar col-2 mr-3">
                <?php if ($image_url) {
                    echo '<img src="'
                        . $image_url
                        . '?' . time() . '" alt="'
                        . $person->given_name
                        . ' '
                        . $person->family_name
                        . __(' Profile Image', 'wicket-acc')
                        . '" />';
                } ?>
            </div>

            <div class="wicket-welcome-content-container col row w-full">
                <div class="wicket-welcome-content col w-full">
                    <p class="wicket-welcome-label">
                        <?php _e('Welcome', 'wicket-acc'); ?>
                    </p>
                    <p class="wicket-welcome-name">
                        <?php $member_name = $person->given_name . ' ' . $person->family_name; ?>
                        <?php echo apply_filters('wicket/acc/block/welcome_block_name', $member_name, $person); ?>
                        <?php do_action('wicket/acc/block/after_welcome_block_name', $person->id); ?>
                    </p>

                    <?php do_action('wicket/acc/block/before_welcome_block_memberships', $person->id); ?>

                    <?php if ($active_memberships) { ?>
                        <?php if ($display_mdp_id): ?>
                            <p class="wicket-welcome-member-mdp-id mb-2">
                                <span><?php echo __('ID:', 'wicket-acc'); ?></span>
                                <?php echo $identifying_number; ?>
                            </p>
                        <?php endif; ?>

                        <div class="gap-6 grid grid-cols-1">
                            <?php
                            // Track seen membership combinations to avoid duplicates
                            $seen_memberships = [];
                        $shown_member_since = false;
                        foreach ($active_memberships as $membership) {
                            // Apply WordPress filter for membership filtering
                            $should_filter = apply_filters('wicket/acc/block/welcome_filter_memberships', false, $membership);

                            if ($should_filter) {
                                continue;
                            }

                            // Create a unique key based on membership name and organization (if present)
                            $membership_key = $membership['name'];
                            if ($membership['type'] == 'organization') {
                                $org_main_info = WACC()->Mdp()->Membership()->getOrganizationMembershipByUuid(
                                    $membership['organization_membership_id']
                                );
                                $org_uuid
                                    = $org_main_info['data']['relationships']['organization']['data']['id'];
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
                                        <?php
                                        $membership_name = $membership['name_' . $current_lang] ?? $membership['name'] ?? ''; // Added fallback and ensure we have a value

                            echo apply_filters(
                                'wicket/acc/block/ac-welcome/membership_name',
                                $membership_name,
                            );
                            ?>
                                    </p>

                                    <?php if ($membership['type'] == 'organization'):
                                        $org_main_info = WACC()->Mdp()->Membership()->getOrganizationMembershipByUuid(
                                            $membership['organization_membership_id']
                                        );

                                        $org_uuid
                                            = $org_main_info['data']['relationships']['organization']['data']['id'];

                                        $org_info = WACC()->Mdp()->Membership()->getActiveMembershipRelationship($org_uuid);

                                        $english_relationship = $org_info['relationship'];
                                        $display_relationship = ($current_lang === 'fr' && isset($relationship_translations[$english_relationship]))
                                            ? $relationship_translations[$english_relationship]
                                            : $english_relationship;
                                        ?>
                                        <p class="mb-0 wicket-welcome-member-org font-bold">
                                            <?php echo esc_html($display_relationship); ?>
                                            &ndash;
                                            <?php echo esc_html($org_info['name_' . $current_lang] ?? $org_info['name']); ?>
                                        </p>
                                    <?php
                                    endif; ?>

                                    <?php if ($membership['type'] == 'individual'):
                                        // For individual memberships, we need to get the relationship from the organization connection
                                        $individual_relationship = '';
                                        if (isset($membership['organization_membership_id'])) {
                                            // Get the organization membership info to find the organization
                                            $org_main_info = WACC()->Mdp()->Membership()->getOrganizationMembershipByUuid(
                                                $membership['organization_membership_id']
                                            );

                                            if (isset($org_main_info['data']['relationships']['organization']['data']['id'])) {
                                                $org_uuid = $org_main_info['data']['relationships']['organization']['data']['id'];
                                                $org_info = WACC()->Mdp()->Membership()->getActiveMembershipRelationship($org_uuid);
                                                $individual_relationship = $org_info['relationship'] ?? '';

                                                // Apply translation if needed
                                                $display_relationship = ($current_lang === 'fr' && isset($relationship_translations[$individual_relationship]))
                                                    ? $relationship_translations[$individual_relationship]
                                                    : $individual_relationship;
                                            }
                                        }

                                        if (!empty($display_relationship)): ?>
                                            <p class="mb-0 wicket-welcome-member-org font-bold">
                                                <?php echo esc_html($display_relationship); ?>
                                                &ndash;
                                                <?php echo esc_html($membership['name_' . $current_lang] ?? $membership['name']); ?>
                                            </p>
                                    <?php endif;
                                    endif; ?>

                                    <p class="mt-0 mb-2 wicket-welcome-member-active flex items-center space-x-2">
                                        <span
                                            class="text-gray-700"><?php echo __('Active Member', 'wicket-acc'); ?></span>
                                    </p>


                                    <?php do_action('wicket/acc/block/welcome/after_member_ids', $person, $membership); ?>

                                    <?php if (
                                        !$shown_member_since
                                        && $member_since
                                        && !empty($membership['starts_at'])
                                        && strtotime($membership['starts_at'])
                                        && !stristr($membership['name'], 'LEADS')
                                    ):
                                        $shown_member_since = true;
                                        ?>
                                        <p class="wicket-welcome-member-since mb-0">
                                            <?php esc_html_e(__('Member Since:', 'wicket-acc')); ?>
                                            <?php if (isset($membership_began_on) && !empty($membership_began_on)) {
                                                echo date('F j, Y', strtotime($membership_began_on));
                                            } else {
                                                echo date('F j, Y', strtotime($membership['starts_at']));
                                            } ?>
                                        </p>
                                    <?php endif; ?>

                                    <?php if ($renewal_date):
                                        if ($renewal_end_timestamp && $renewal_end_timestamp > 0): ?>
                                            <p class="wicket-welcome-renewal mb-0">
                                                <?php echo __('Renewal Date:', 'wicket-acc'); ?>
                                                <?php echo date('F j, Y', $renewal_end_timestamp); ?>
                                            </p>
                                    <?php
                                        endif;
                                    endif; ?>
                                </div>

                            <?php } ?>
                        </div>
                    <?php } else { ?>
                        <p class="wicket-welcome-pending-membership">
                            <?php echo apply_filters('wicket/acc/block/welcome_non_member_text', __('Non-Member', 'wicket-acc')); ?>
                        </p>
                    <?php } ?>

                    <?php do_action('wicket/acc/block/after_welcome_block_memberships', $person->id); ?>

                </div>
                <?php if (
                    $edit_profile
                    && isset($editprofile_page_link)
                    && isset($editprofile_page_title)
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
