<?php

/**
 * Wicket Profile Picture Block
 *
 **/

namespace Wicket_AC\Blocks\AC_Profile_Picture_Block;

if (!class_exists('Wicket_Acc_Profile_Picture')) {
	class Wicket_Acc_Profile_Picture
	{
		/**
		 * Constructor
		 */
		public function __construct(
			protected int $pp_max_size = 0,
			protected string $pp_uploads_path = '',
			protected string $pp_uploads_url = '',
			protected string $pp_uploads_subdir = '',
			protected array $uploads_dir = [],
			protected array $pp_extensions = []
		) {
			$this->uploads_dir       = wp_get_upload_dir();
			$this->pp_max_size       = absint(get_field('profile_picture_max_size'));         // in MB
			$this->pp_uploads_path   = $this->uploads_dir['basedir'] . '/wicket-profile-pictures';
			$this->pp_uploads_url    = $this->uploads_dir['baseurl'] . '/wicket-profile-pictures';
			$this->pp_uploads_subdir = 'wicket-profile-pictures';
			$this->pp_extensions     = ['jpg', 'jpeg', 'png', 'gif'];

			// Display the block
			$this->display_block();

			// Change WP get_avatar_url behavior
			add_filter('get_avatar_url', [$this, 'get_avatar'], 10, 3);
		}

		protected function display_block()
		{
			$this->process_form();

			$pp_profile_picture = $this->get_profile_picture();
?>

			<section class="container wicket-ac-profile-picture">
				<h2>
					<?php esc_html_e('Profile Image', 'wicket-acc'); ?>
				</h2>
				<div class="profile-image">
					<img src="<?php echo $pp_profile_picture; ?>" alt="<?php esc_html_e('Profile Image', 'wicket-acc'); ?>" class="profile-image-img">
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
<?php
		}

		/**
		 * Get the profile picture URL
		 *
		 * @return string|bool Profile picture URL, default one or false on error
		 */
		protected function get_profile_picture()
		{
			// Get current WP user ID
			$current_user_id = get_current_user_id();

			// Guest?
			if (is_wp_error($current_user_id)) {
				return false;
			}

			// Check for jpg or png
			$extensions = $this->pp_extensions;
			$pp_profile_picture = '';
			$pp_valid_extension = '';

			foreach ($extensions as $ext) {
				$file_path = $this->uploads_dir['basedir'] . '/wicket-profile-pictures/' . $current_user_id . '.' . $ext;
				if (file_exists($file_path)) {
					// Found it!
					$pp_profile_picture = $file_path;
					$pp_valid_extension = $ext;
					break;
				}
			}

			// Get file URL
			if (!empty($pp_valid_extension)) {
				$pp_profile_picture = $this->uploads_dir['baseurl'] . '/wicket-profile-pictures/' . $current_user_id . '.' . $pp_valid_extension;
			}

			// Still no image? Return the default svg
			if (empty($pp_profile_picture)) {
				$pp_profile_picture = WICKET_ACC_PLUGIN_URL . '/assets/img/profile-picture-default.svg';
			}

			return $pp_profile_picture;
		}

		/**
		 * Process the form and save the profile picture
		 *
		 * @return bool
		 */
		public function process_form()
		{
			if (is_admin()) {
				return;
			}

			// No data? no action?
			if (!isset($_POST['action']) || $_POST['action'] !== 'wicket-ac-profile-picture-form') {
				return;
			}

			$form = $_POST;

			// Check nonce
			if (!wp_verify_nonce(sanitize_text_field(wp_unslash($form['nonce'])), 'wicket-ac-profile-picture-form')) {
				return false;
			}

			// Check if the file is set and valid
			if (!isset($_FILES['profile-image']) || $_FILES['profile-image']['error'] !== UPLOAD_ERR_OK) {
				return false;
			}

			// Get the extension
			$file_extension = pathinfo($_FILES['profile-image']['name'], PATHINFO_EXTENSION);

			// Check if is a valid image
			if (getimagesize($_FILES['profile-image']['tmp_name']) === false) {
				return false;
			}

			// Check if the file size is too big. pp_max_size is in MB
			if ($_FILES['profile-image']['size'] > $this->pp_max_size * 1024 * 1024) { // Convert MB to bytes
				return false;
			}

			// User ID
			$user_id = sanitize_text_field(wp_unslash($form['user_id']));

			// Remove any existing file on wicket-profile-pictures/{user_id}.{extension}
			$file_path   = $this->uploads_dir['basedir'] . '/wicket-profile-pictures/' . $user_id . '.' . $file_extension;

			// Delete the file if it exists
			foreach ($this->pp_extensions as $ext) {
				$other_file_path = $this->uploads_dir['basedir'] . '/wicket-profile-pictures/' . $user_id . '.' . $ext;

				if (file_exists($other_file_path)) {
					wp_delete_file($other_file_path);
				}
			}

			// No matter whats the file name, rename it to {user_id}.{extension}
			$_FILES['profile-image']['name']      = $user_id . '.' . $file_extension;
			$_FILES['profile-image']['full_path'] = $user_id . '.' . $file_extension;

			// Create subfolder if it doesn't exist
			if (!file_exists($this->uploads_dir['basedir'] . '/wicket-profile-pictures')) {
				wp_mkdir_p($this->uploads_dir['basedir'] . '/wicket-profile-pictures');
			}

			// Move the file to the uploads directory, move_uploaded_file
			$movefile = move_uploaded_file($_FILES['profile-image']['tmp_name'], $file_path);
			//Crop to square
			$file_path_new = $this->crop_center_of_rectangle_from_file($file_path, $file_path);
			// Replace with cropped file
			unlink($file_path);
			$movefile = rename($file_path_new, $file_path);

			// Check for errors
			if (!$movefile) {
				return false;
			}

			return true;
		}
		/**
		 * Crop square from the center of an image file
		 *
		 * @param string $src_file    Path to the source image file.
		 * @param string $dst_file    Path to save the cropped image.
		 * @return string|false       Path to the cropped image file on success, false on failure.
		 */
		function crop_center_of_rectangle_from_file($src_file, $dst_file)
		{
			list($src_width, $src_height) = getimagesize($src_file);

			// Determine the crop dimensions
			if ($src_width > $src_height) {
				$crop_x = ($src_width - $src_height) / 2;
				$crop_y = 0;
				$crop_size = $src_height;
			} else {
				$crop_x = 0;
				$crop_y = ($src_height - $src_width) / 2;
				$crop_size = $src_width;
			}

			if (!function_exists('wp_crop_image')) {
				include(ABSPATH . 'wp-admin/includes/image.php');
			}

			// Crop the image using wp_crop_image
			$cropped = wp_crop_image($src_file, $crop_x, $crop_y, $crop_size, $crop_size, $crop_size, $crop_size, false, $dst_file);

			return $cropped;
		}

		/**
		 * Changes default WP get_avatar_url behavior
		 *
		 * @param string $avatar_url
		 * @param mixed $id_or_email
		 * @param array $args
		 *
		 * @return string
		 */
		public function get_avatar($avatar_url, $id_or_email, $args = [])
		{
			// Get the profile picture URL
			$pp_profile_picture = $this->get_profile_picture();

			// If the profile picture URL is not empty, return it
			if (!empty($pp_profile_picture)) {
				// Filter URL (for child themes to manipulate) and return
				return apply_filters('wicket/acc/get_avatar', $pp_profile_picture);
			}

			// Otherwise, return the default avatar
			return apply_filters('wicket/acc/get_avatar', $avatar_url);
		}
	} // end Wicket_Acc_Profile_Picture class
}

/**
 * Initialize the block
 *
 * @param array $block
 */
function init($block = [])
{
	// Is ACF enabled?
	if (function_exists('acf_get_field')) {
		new Wicket_Acc_Profile_Picture();
	}
}
