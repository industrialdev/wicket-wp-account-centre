<?php

namespace WicketAcc;

// No direct access
defined('ABSPATH') || exit;

/**
 * Wicket Profile Picture Block
 *
 **/
class Block_ProfilePictureChange extends WicketAcc
{
	/**
	 * Constructor
	 */
	public function __construct(
		protected array $block                = [],
		protected bool $is_preview            = false,
		protected ?Blocks $blocks = null,
		protected int $pp_max_size            = 0,
		protected string $pp_uploads_path     = '',
		protected string $pp_uploads_url      = '',
		protected array $pp_extensions        = []
	) {
		$this->block        = $block;
		$this->is_preview   = $is_preview;
		$this->blocks       = $blocks ?? new Blocks();

		$this->pp_max_size     = absint(get_field('acc_profile_picture_size', 'option'));  // in MB
		$this->pp_uploads_path = WICKET_ACC_UPLOADS_PATH . 'profile-pictures/';
		$this->pp_uploads_url  = WICKET_ACC_UPLOADS_URL . 'profile-pictures/';
		$this->pp_extensions   = ['jpg', 'jpeg', 'png', 'gif'];

		// Display the block
		$this->display_block();
	}

	/**
	 * Display the block
	 *
	 * @return void
	 */
	protected function display_block()
	{
		// Process the form
		$process_form = $this->process_form();
		$remove_form  = $this->remove_form();

		if ($process_form === false) {
			$this->blocks->render_template('profile-picture-change_error');
		}

		if ($process_form === true) {
			$this->blocks->render_template('profile-picture-change_success');
		}

		// Get user profile picture
		$pp_profile_picture = WACC()->Profile->get_profile_picture();
		$pp_is_custom       = WACC()->Profile->is_custom_profile_picture($pp_profile_picture);

		$args = [
			'is_custom'   => $pp_is_custom,
			'pp_url'      => $pp_profile_picture,
			'pp_max_size' => $this->pp_max_size
		];

		// Render block
		$this->blocks->render_template('profile-picture-change', $args);
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
		if (!isset($_POST['action']) || $_POST['action'] !== 'wicket-acc-profile-picture-form') {
			return;
		}

		$form = $_POST;

		// Check nonce
		if (!wp_verify_nonce(sanitize_text_field(wp_unslash($form['nonce'])), 'wicket-acc-profile-picture-form')) {
			return false;
		}

		// Check if the file is set and valid
		if (!isset($_FILES['profile-image']) || $_FILES['profile-image']['error'] !== UPLOAD_ERR_OK) {
			return false;
		}

		// Get the extension
		$file_extension = pathinfo($_FILES['profile-image']['name'], PATHINFO_EXTENSION);

		// Check if is a valid image
		if (@getimagesize($_FILES['profile-image']['tmp_name']) === false) {
			return false;
		}

		// Check if the file size is too big. pp_max_size is in MB
		if ($_FILES['profile-image']['size'] > $this->pp_max_size * 1024 * 1024) { // Convert MB to bytes
			return false;
		}

		// User ID
		$user_id = sanitize_text_field(wp_unslash($form['user_id']));

		// Remove any existing file on wicket-profile-pictures/{user_id}.{extension}
		$file_path = $this->pp_uploads_path .  $user_id . '.' . $file_extension;

		// Delete the file if it exists
		foreach ($this->pp_extensions as $ext) {
			$other_file_path = $this->pp_uploads_path . $user_id . '.' . $ext;

			if (file_exists($other_file_path)) {
				wp_delete_file($other_file_path);
			}
		}

		// No matter whats the file name, rename it to {user_id}.{extension}
		$_FILES['profile-image']['name']      = $user_id . '.' . $file_extension;
		$_FILES['profile-image']['full_path'] = $user_id . '.' . $file_extension;

		// Create subfolder if it doesn't exist
		if (!file_exists($this->pp_uploads_path)) {
			wp_mkdir_p($this->pp_uploads_path);
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
	 * Process the remove form and delete the profile picture
	 *
	 * @return bool
	 */
	public function remove_form()
	{
		if (is_admin()) {
			return;
		}

		// No data? no action?
		if (!isset($_POST['action']) || $_POST['action'] !== 'wicket-acc-profile-picture-remove-form') {
			return;
		}

		$form = $_POST;

		// Check nonce
		if (!wp_verify_nonce(sanitize_text_field(wp_unslash($form['nonce'])), 'wicket-acc-profile-picture-remove-form')) {
			return false;
		}

		// User ID
		$user_id = absint($form['user_id']);

		// Remove any existing file on wicket-profile-pictures/{user_id}.{extension}
		foreach ($this->pp_extensions as $ext) {
			$file_path = $this->pp_uploads_path . $user_id . '.' . $ext;

			if (file_exists($file_path)) {
				wp_delete_file($file_path);
			}
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
	protected function crop_center_of_rectangle_from_file($src_file, $dst_file)
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
}
