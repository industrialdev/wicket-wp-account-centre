<?php

namespace WicketAcc;

// No direct access
defined('ABSPATH') || exit;

/**
 * Wicket Welcome Block
 **/

class Block_Welcome extends WicketAcc
{
    /**
     * Constructor
     */
    public function __construct(
        protected array $block     = [],
        protected bool $is_preview = false,
    ) {
        $this->block      = $block;
        $this->is_preview = $is_preview;

        // Display the block
        $this->init_block();
    }

    /**
     * Init block
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

        $current_user             = wp_get_current_user();
        $person                   = wicket_current_person();
        $identifying_number       = $person->identifying_number;
        $edit_profile             = get_field('edit_profile_button');
        $edit_profile_button_link = get_field('edit_profile_button_link');
        $member_since             = get_field('member_since');
        $renewal_date             = get_field('renewal_date');
        $display_mdp_id           = get_field('display_mdp_id');
        $image_url                = get_avatar_url($current_user->ID, ['size' => '300']);
        $active_memberships       = wicket_get_active_memberships($iso_code);

        // Edit profile button (link and title)
        if (empty($edit_profile_button_link) || !is_array($edit_profile_button_link)) {
            // Use ACC mapping
            $acc_editprofile_page   = get_field('acc_page_edit-profile', 'option');
            $editprofile_page_link  = get_permalink($acc_editprofile_page);
            $editprofile_page_title = get_the_title($acc_editprofile_page);
        } else {
            // Use user defined URL
            $editprofile_page_link  = $edit_profile_button_link['url'];
            $editprofile_page_title = $edit_profile_button_link['title'];
        }
        ?>

		<div class="wicket-acc-block wicket-acc-block-welcome wp-block-wicket-acc-callout bg-light-010 row">
			<div class="wicket-welcome-avatar col-2">
				<?php if ($image_url) {
				    echo '<img src="' . $image_url . '" alt="' . $person->given_name . " " . $person->family_name . __(' Profile Image', 'wicket-acc') . '" />';
				} ?>
			</div>

			<div class="wicket-welcome-content-container col row">
				<div class="wicket-welcome-content col">
					<p class="wicket-welcome-label"><?php _e('Welcome', 'wicket-acc'); ?></p>
					<p class="wicket-welcome-name"><?php echo $person->given_name . " " . $person->family_name; ?></p>

					<?php
				    if ($active_memberships) {
				        foreach ($active_memberships as $membership) {
				            if (function_exists('wicket_ac_welcome_filter_memberships')) {
				                if (wicket_ac_welcome_filter_memberships($membership)) {
				                    continue;
				                }
				            }
				            ?>
							<div class="my-0 wicket-welcome-memberships">
								<p class="mb-0 wicket-welcome-member-type">
									<strong><?php echo __('Membership Type:', 'wicket-acc'); ?></strong> <?php echo $membership['name']; ?>
								</p>

								<?php
				                        if ($membership['type'] == 'organization') :
				                            $org_main_info = WACC()->MdpApi->get_organization_membership_by_uuid($membership['organization_membership_id']);
				                            $org_uuid = $org_main_info['data']['relationships']['organization']['data']['id'];

				                            $org_info = wicket_get_active_memberships_relationship($org_uuid);
				                            ?>
									<p class="mb-0 wicket-welcome-member-org">
										<strong><?php echo $org_info['relationship']; ?> &ndash; <?php echo $org_info['name']; ?></strong>
									</p>
								<?php endif; ?>

								<p class="mt-0 mb-2 wicket-welcome-member-active flex items-center space-x-2">
									<span class="green-circle inline-block w-3 h-3 mr-1 bg-green-500 rounded-full"></span>
									<span class="text-gray-700"><?php echo __('Active Member', 'wicket-acc'); ?></span>
								</p>

								<?php if ($display_mdp_id) : ?>
									<p class="wicket-welcome-member-mdp-id mb-0">
										<span><?php echo __('ID:', 'wicket-acc'); ?></span>
										<?php echo $identifying_number; ?>
									</p>
								<?php endif; ?>

								<?php if ($member_since && !empty($membership['starts_at']) && strtotime($membership['starts_at'])) : ?>
									<p class="wicket-welcome-member-since mb-0">
										<?php echo __('Member Since:', 'wicket-acc'); ?> <?php echo date('F j, Y', strtotime($membership['starts_at'])); ?>
									</p>
								<?php endif; ?>

								<?php if ($renewal_date && !empty($membership['ends_at']) && strtotime($membership['ends_at'])) : ?>
									<p class="wicket-welcome-renewal mb-0">
										<?php echo __('Renewal Date:', 'wicket-acc'); ?> <?php echo date('F j, Y', strtotime($membership['ends_at'])); ?>
									</p>
								<?php endif; ?>
							</div>
					<?php
				        }
				    }
        ?>
				</div>
				<?php if ($edit_profile && isset($editprofile_page_link) && isset($editprofile_page_title)) {
				    ?>
					<div class="wicket-welcome-edit-profile-button col-3 text-right">
						<a href="<?php echo $editprofile_page_link; ?>" class="button button--secondary text-center"><i class="fa-regular fa-pen-to-square icon-r" aria-hidden="true"></i><?php echo $editprofile_page_title; ?></a>
					</div>
				<?php } ?>
			</div>
		</div>
<?php
    }
}
