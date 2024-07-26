<?php
// No direct access
defined('ABSPATH') || exit;

/**
 * Available $args[] variables:
 *
 * pp_profile_picture - Profile picture URL
 */
?>
<section class="container wicket-ac-profile-picture">
	<h2>
		<?php esc_html_e('Profile Image', 'wicket-acc'); ?>
	</h2>
	<div class="profile-image">
		<img src="<?php echo $args['pp_profile_picture']; ?>" alt="<?php esc_html_e('Profile Image', 'wicket-acc'); ?>" class="profile-image-img">
	</div>
	<form name="wicket-ac-profile-picture-form" method="post" enctype="multipart/form-data">
		<label for="profile-image" class="sr-only">
			<?php esc_html_e('Choose File', 'wicket-acc'); ?>
		</label>
		<input type="file" id="profile-image" name="profile-image" class="sr-only">
		<div class="buttons">
			<label for="profile-image" class="btn choose-file">
				<?php esc_html_e('Choose File', 'wicket-acc'); ?>
			</label>
			<button type="submit" class="btn update-image">
				<?php esc_html_e('Update Image', 'wicket-acc'); ?>
			</button>
		</div>
		<?php wp_nonce_field('wicket-ac-profile-picture-form', 'nonce'); ?>
		<input type="hidden" name="user_id" value="<?php echo get_current_user_id(); ?>">
		<input type="hidden" name="action" value="wicket-ac-profile-picture-form">
	</form>
</section>
