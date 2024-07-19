<?php

/**
 * Wicket Profile Picture Block
 *
 **/

namespace Wicket_AC\Blocks\AC_Profile_Picture_Block;

if (!class_exists('Wicket_Acc_Profile_Picture')) {
	class Wicket_Acc_Profile_Picture
	{
		protected $pp_enabled;
		protected $pp_max_size;
		protected $pp_uploads_path;
		protected $pp_uploads_url;
		protected $pp_uploads_subdir;

		/**
		 * Constructor
		 */
		public function __construct()
		{
			$uploads_dir = wp_get_upload_dir();

			$this->pp_enabled      = get_field('profile_picture_enabled');
			$this->pp_max_size     = get_field('profile_picture_max_size');
			$this->pp_uploads_path = $uploads_dir['basedir'] . '/wicket-profile-pictures';
			$this->pp_uploads_url  = $uploads_dir['baseurl'] . '/wicket-profile-pictures';
			$this->pp_uploads_subdir = 'wicket-profile-pictures';

			$this->display_block();

			// Hook to process the form when the block is displayed
			add_action('init', [$this, 'process_form']);
		}

		protected function display_block()
		{
			if ($this->pp_enabled) {
				$pp_profile_picture = $this->get_profile_picture();
			}
?>
			<div class="wicket-ac-profile-picture">
				<?php if ($this->pp_enabled) : ?>
					<div class="max-w-md mx-auto bg-white p-6 rounded-lg">
						<h2 class="text-2xl font-bold mb-4">
							<?php _e('Profile Image', 'wicket-acc'); ?>
						</h2>
						<div class="flex flex-col items-center">
							<img src="<?php echo $pp_profile_picture; ?>" alt="<?php _e('Profile Image', 'wicket-acc'); ?>" class="w-24 h-24 rounded-full mb-4 wicket-ac-profile-picture-image">
							<form name="wicket-ac-profile-picture-form" class="w-full" action="<?php echo esc_url(get_permalink()); ?>" method="post">
								<label for="profile-image" class="sr-only">
									<?php _e('Choose File', 'wicket-acc'); ?>
								</label>
								<input type="file" id="profile-image" name="profile-image" class="sr-only">
								<div class="flex justify-between">
									<label for="profile-image" class="flex items-center px-4 py-2 bg-gray-200 text-gray-700 rounded-md cursor-pointer hover:bg-gray-300">
										<?php _e('Choose File', 'wicket-acc'); ?>
									</label>
									<button type="submit" class="flex items-center px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 focus:ring-2 focus:ring-blue-300 focus:outline-none">
										<?php _e('Update Image', 'wicket-acc'); ?>
									</button>
								</div>
								<input type="hidden" name="nonce" value="<?php echo wp_create_nonce('wicket-ac-profile-picture-form'); ?>">
								<input type="hidden" name="user_id" value="<?php echo get_current_user_id(); ?>">
								<input type="hidden" name="action" value="wicket-ac-profile-picture-form">
							</form>
						</div>
					</div>
				<?php else : ?>
					<div class="max-w-md mx-auto bg-white p-6 rounded-lg">
						<p><?php _e('Profile pictures are not enabled', 'wicket-acc'); ?></p>
					</div>
				<?php endif; ?>
			</div>
<?php
		}

		/**
		 * Get the profile picture
		 *
		 * @return string|bool Profile picture URL, default one or false on error
		 */
		protected function get_profile_picture()
		{
			global $uploads_dir;

			// Get current WP user ID
			$current_user_id = get_current_user_id();

			// Guest?
			if (is_wp_error($current_user_id)) {
				return false;
			}

			// Check for jpg or png
			$extensions         = ['jpg', 'png'];
			$pp_profile_picture = null;

			foreach ($extensions as $ext) {
				$file_path = $uploads_dir['basedir'] . '/wicket-profile-pictures/' . $current_user_id . '.' . $ext;
				if (file_exists($file_path)) {
					// Found it!
					$pp_profile_picture = $file_path;
					break;
				}
			}

			// Still no image? Return the default svg
			if (is_null($pp_profile_picture)) {
				$pp_profile_picture = WICKET_ACC_PLUGIN_DIR . '/assets/img/profile-picture-default.svg';
			}

			return $pp_profile_picture;
		}

		/**
		 * Process the form and save the profile picture
		 *
		 * @param array $form
		 *
		 * @return bool
		 */
		protected function process_form()
		{
			global $uploads_dir;

			// No data? no action?
			if (empty($_POST) && !isset(($_POST['action'] && $_POST['action'] == 'wicket-ac-profile-picture-form'))) {
				return;
			}

			$form = $_POST['wicket-ac-profile-picture-form'];

			// Check nonce
			if (!wp_verify_nonce(sanitize_text_field(wp_unslash($form['nonce'])), 'wicket-ac-profile-picture-form')) {
				return false;
			}

			// Check if the file is set
			if (empty($form['profile-image'])) {
				return false;
			}

			// Check if the file is too big
			if (filesize(wp_unslash($form['profile-image']['tmp_name'])) > $this->pp_max_size) {
				return false;
			}

			// The file
			$file    = wp_unslash($form['profile-image']);

			// User ID
			$user_id = sanitize_text_field(wp_unslash($form['user_id']));

			// Remove any existing file on wicket-profile-pictures/{user_id}.{extension}
			$file_path   = $uploads_dir['basedir'] . '/wicket-profile-pictures/' . $user_id . '.' . $file['extension'];

			// Delete the file if it exists
			if (file_exists($file_path)) {
				wp_delete_file($file_path);
			}

			// No matter whats the file name, rename it to {user_id}.{extension}
			$file['name'] = $user_id . '.' . $file['extension'];

			// Create subfolder if it doesn't exist
			if (!file_exists($uploads_dir['basedir'] . '/wicket-profile-pictures')) {
				wp_mkdir_p($uploads_dir['basedir'] . '/wicket-profile-pictures');
			}

			// Include WordPress file handling functions
			require_once(ABSPATH . 'wp-admin/includes/file.php');
			require_once(ABSPATH . 'wp-admin/includes/image.php');
			require_once(ABSPATH . 'wp-admin/includes/media.php');

			// Temporary change uploads path
			add_filter('upload_dir', [$this, 'change_uploads_directory']);

			// Move the file to the uploads directory
			$movefile = wp_handle_upload($file, ['test_form' => false]);

			// Check for errors
			if (!$movefile || is_wp_error($movefile)) {
				return false;
			}

			// Check if the file is valid jpg or png
			if (!wp_check_filetype($movefile['file'], $movefile['type'])) {
				return false;
			}

			// Return uploads path to normal
			remove_filter('upload_dir', [$this, 'change_uploads_directory']);

			return true;
		}

		/**
		 * Change uploads directory
		 *
		 * @return void
		 */
		protected function change_uploads_directory($dir)
		{
			return [
				'path'     => $this->pp_uploads_path,
				'url'      => $this->pp_uploads_url,
				'subdir'   => $this->pp_uploads_subdir,
			] + $dir;
		}
	}
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
