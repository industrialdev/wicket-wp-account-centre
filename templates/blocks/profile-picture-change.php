<?php
// No direct access
defined('ABSPATH') || exit;

/**
 * Available $args[] variables:
 *
 * pp_url - Profile picture URL
 * pp_max_size - Max upload size in MB
 */
?>
<section class="container wicket-ac-profile-picture">
	<h2>
		<?php esc_html_e('Profile Image', 'wicket-acc'); ?>
	</h2>
	<div class="profile-image">
		<img src="<?php echo $args['pp_url']; ?>" alt="<?php esc_html_e('Profile Image', 'wicket-acc'); ?>" class="profile-image-img">
	</div>
	<form name="wicket-ac-profile-picture-form" method="post" enctype="multipart/form-data">
		<label for="profile-image" class="sr-only">
			<?php esc_html_e('Choose File', 'wicket-acc'); ?>
		</label>
		<input type="file" id="profile-image" name="profile-image" class="sr-only" accept="image/png, image/gif, image/jpeg">
		<div class="guidance text-sm">
			<?php esc_html_e('Upload a profile picture to personalize your profile. The image will be cropped to a square.', 'wicket-acc'); ?>
			<?php esc_html_e('Max upload size:', 'wicket-acc'); ?> <?php echo $args['pp_max_size']; ?> <?php esc_html_e('MB', 'wicket-acc'); ?>
		</div>
		<div class="buttons">
			<label for="profile-image" class="btn choose-file">
				<?php esc_html_e('Choose File', 'wicket-acc'); ?>
			</label>
			<button type="submit" class="btn update-image" id="update-image" disabled="disabled">
				<?php esc_html_e('Update Image', 'wicket-acc'); ?>
			</button>
		</div>
		<div id="file-alert" class="file-alert" style="display: none;">
			<?php esc_html_e('File selected. Ready to upload!', 'wicket-acc'); ?>
		</div>
		<?php wp_nonce_field('wicket-ac-profile-picture-form', 'nonce'); ?>
		<input type="hidden" name="user_id" value="<?php echo get_current_user_id(); ?>">
		<input type="hidden" name="action" value="wicket-ac-profile-picture-form">
	</form>
</section>
<script>
	document.addEventListener('DOMContentLoaded', function() {
		var fileInput = document.getElementById('profile-image');
		var fileAlert = document.getElementById('file-alert');

		fileInput.addEventListener('change', function() {
			if (fileInput.files.length > 0) {
				fileAlert.style.display = 'block';
			} else {
				fileAlert.style.display = 'none';
			}
		});

		// On image selection, enable the update button
		fileInput.addEventListener('change', function() {
			document.getElementById('update-image').disabled = false;
		});
	});
</script>
