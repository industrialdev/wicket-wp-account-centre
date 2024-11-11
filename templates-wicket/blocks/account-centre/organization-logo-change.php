<?php

namespace WicketAcc;

// No direct access
defined('ABSPATH') || exit;

/**
 * Available $args[] variables:
 *
 * organization_logo_url - Organization logo URL
 */

$org_id = $_GET['org_id'] ?? '';

if (! $org_id) {
    return;
}

$organization_logo_url = $args['organization_logo_url'] ?? '';
$max_upload_size       = $args['max_upload_size'] ?? '';

?>
<section class="container wicket-acc-org-logo-change">
	<h2>
		<?php esc_html_e('Organization Logo', 'wicket-acc'); ?>
	</h2>
	<div class="org-logo">
		<?php if ($organization_logo_url) : ?>
			<img src="<?php echo $organization_logo_url; ?>?<?php echo time(); ?>"
				alt="<?php esc_html_e('Profile Image', 'wicket-acc'); ?>" class="org-logo-img">

			<form name="wicket-acc-org-profile-picture-remove-form" method="post">
				<input type="hidden" name="org_id" value="<?php echo $org_id; ?>">
				<input type="hidden" name="action" value="wicket-acc-org-profile-picture-remove-form">
				<?php wp_nonce_field('wicket-acc-org-profile-picture-remove-form', 'nonce'); ?>
				<button type="submit" class="remove-image circle-x"
					title="<?php esc_html_e('Remove Image', 'wicket-acc'); ?>">x</button>
			</form>
		<?php endif; ?>
	</div>
	<form name="wicket-acc-org-logo-form" method="post" enctype="multipart/form-data">
		<label for="org-logo" class="sr-only">
			<?php esc_html_e('Choose File', 'wicket-acc'); ?>
		</label>
		<input type="file" id="org-logo" name="org-logo" class="sr-only" accept="image/png, image/gif, image/jpeg">
		<div class="guidance text-sm">
			<?php esc_html_e('Upload an organization logo to represent your organization.', 'wicket-acc'); ?>
			<?php esc_html_e('Max upload size:', 'wicket-acc'); ?> <?php echo $max_upload_size; ?>
			<?php esc_html_e('MB', 'wicket-acc'); ?>
		</div>
		<div class="buttons">
			<label for="org-logo" class="btn choose-file">
				<?php esc_html_e('Choose File', 'wicket-acc'); ?>
			</label>
			<button type="submit" class="btn update-image" id="update-image" disabled="disabled">
				<?php esc_html_e('Update Image', 'wicket-acc'); ?>
			</button>
		</div>
		<div id="file-alert" class="file-alert" style="display: none;">
			<?php esc_html_e('File selected. Ready to upload!', 'wicket-acc'); ?>
		</div>
		<?php wp_nonce_field('wicket-acc-org-logo-form', 'nonce'); ?>
		<input type="hidden" name="org_id" value="<?php echo $org_id; ?>">
		<input type="hidden" name="action" value="wicket-acc-org-logo-form">
	</form>
</section>
<script>
	document.addEventListener('DOMContentLoaded', function () {
		var fileInput = document.getElementById('org-logo');
		var fileAlert = document.getElementById('file-alert');

		fileInput.addEventListener('change', function () {
			if (fileInput.files.length > 0) {
				fileAlert.style.display = 'block';
			} else {
				fileAlert.style.display = 'none';
			}
		});

		// On image selection, enable the update button
		fileInput.addEventListener('change', function () {
			document.getElementById('update-image').disabled = false;
		});
	});
</script>
