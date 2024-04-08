<?php
/**
 * Wicket Welcome Block
 *
 **/

namespace Wicket_AC\Blocks\AC_Welcome_Block;

function init( $block = [] ) { 

		$current_user = wp_get_current_user();
		$person = wicket_current_person();
		$biography = '';

		//$active_memberships = wicket_get_active_memberships();
		?>

		<div class="bg-light-030 bg-opacity-10 rounded-100 py-8 px-6">
			<div class="md:flex">
				<div class="w-1/4 welcome-avatar">
					<?php //bp_displayed_user_avatar( 'type=full' ); ?>
				</div>

				<div class="w-3/4">
					<div class="md:ps-5">
						<p class="text-heading-xs"><?php _e('Welcome', 'wicket'); ?></p>
						<p class="text-heading-lg font-bold"><?php echo $person->given_name . " " . $person->family_name; ?></p>

						<?php 
						// if($active_memberships){
						// 	foreach($active_memberships as $membership){
						// 		echo '<hr aria-hidden="true" />';
						// 		echo '<p class="mt-4 mb-2"><strong>Membership:</strong> ' . $membership['name'] . '</p>';
						// 		echo '<p><strong>Member Since:</strong> ' . date('F j, Y', strtotime($membership['starts_at'])) . '</p>';
						// 	}
						// }
						?>
					</div>
				</div>
			</div>
		</div>
	
	<?php
}