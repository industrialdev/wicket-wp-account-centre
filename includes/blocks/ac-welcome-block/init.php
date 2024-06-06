<?php
/**
 * Wicket Welcome Block
 *
 **/

namespace Wicket_AC\Blocks\AC_Welcome_Block;

function init( $block = [] ) { 

		$current_user = wp_get_current_user();
		$person = wicket_current_person();
		$edit_profile = get_field('edit_profile_button');
		$edit_profile_button = get_field('edit_profile_button_link');
		$member_since = get_field('member_since');
		$renewal_date = get_field('renewal_date');
		$image_url = get_avatar_url($current_user->ID, ['size' => '300']);
		$active_memberships = wicket_get_active_memberships();
		?>

		<div class="wicket-welcome-block bg-light-010 rounded-100">
			<div class="wicket-acc-flex">
				<div class="wicket-welcome-avatar">
					<?php if($image_url){
						echo '<img src="'.$image_url.'" alt="'. $person->given_name . " " . $person->family_name . __(' Profile Image', 'wicket') . '" />';
					} ?>
				</div>

				<div class="wicket-acc-flex wicket-welcome-content-container wicket-align-item-start">
					<div class="wicket-welcome-content">
							<p class="text-heading-xs wicket-welcome-label"><?php _e('Welcome', 'wicket'); ?></p>
							<p class="text-heading-lg wicket-welcome-name"><?php echo $person->given_name . " " . $person->family_name; ?></p>

							<?php 
							if($active_memberships){
								foreach($active_memberships as $membership){
                  if( function_exists('wicket_ac_welcome_filter_memberships') ) {
                    if( wicket_ac_welcome_filter_memberships( $membership ) ) {
                      continue;
                    }
                  }              
									echo '<div class="mt-4 wicket-welcome-memberships">';

									echo '<div class="mb-4">';
										echo '<p class="mb-1 wicket-welcome-member-type"><strong>' . __('Membership Type:', 'wicket') . '</strong> ' . $membership['name'] . '</p>';
										if($membership['type'] == 'organization'){
											$org_info = wicket_get_active_memberships_relationship();
											echo '<p class="mb-2 wicket-welcome-member-org"><strong>' . $org_info['relationship'] . ' &ndash; ' . $org_info['name'] . '</strong></p>';
										}
										echo '<p class="mt-2 mb-1 wicket-welcome-member-active">' . __('Active Member', 'wicket') . '</p>';
									echo '</div>';
									if($member_since){
										echo '<p class="mb-1 wicket-welcome-member-since">' . __('Member Since:', 'wicket') . ' ' . date('F j, Y', strtotime($membership['starts_at'])) . '</p>';
									}
									if($renewal_date){
										echo '<p class="mb-1 wicket-welcome-renewal">' . __('Renewal Date:', 'wicket') . ' ' . date('F j, Y', strtotime($membership['ends_at'])) . '</p>';
									}
									echo '</div>';
								}
							}
							?>
					</div>
					<?php if($edit_profile && isset($edit_profile_button['url']) && isset($edit_profile_button['title']) ){
					?>
						<a href="<?php echo $edit_profile_button['url']; ?>" class="button wicket-button"><i class="fa-regular fa-pen-to-square icon-r" aria-hidden="true"></i><?php echo $edit_profile_button['title']; ?></a>
					<?php } ?>
				</div>
			</div>
		</div>
	
	<?php
}