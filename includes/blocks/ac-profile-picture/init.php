<?php

namespace WicketAcc\Blocks\ProfilePicture;

use WicketAcc\Blocks;

// No direct access
defined('ABSPATH') || exit;

/**
 * Wicket Profile Picture Block.
 **/
class init extends Blocks
{
    /**
     * Constructor.
     */
    public function __construct(
        protected array $block = [],
        protected bool $is_preview = false,
        protected ?Blocks $blocks = null,
        protected int $pp_max_size = 1,
        protected string $pp_uploads_path = '',
        protected string $pp_uploads_url = '',
        protected array $pp_extensions = [],
        protected ?string $error_message = null,
        protected ?string $error_type = null
    ) {
        $this->block = $block;
        $this->is_preview = $is_preview;
        $this->blocks = $blocks ?? new Blocks();

        // Get max size from Carbon Fields setting
        if (function_exists('carbon_get_theme_option')) {
            $this->pp_max_size = absint(carbon_get_theme_option('acc_profile_picture_size'));
        } else {
            // Fallback to ACF if Carbon Fields is not available
            $this->pp_max_size = absint(get_field('acc_profile_picture_size', 'option'));
        }

        // Ensure we have a valid max size (minimum 1MB)
        $this->pp_max_size = max(1, $this->pp_max_size);
        $this->pp_uploads_path = WICKET_ACC_UPLOADS_PATH . 'profile-pictures/';
        $this->pp_uploads_url = WICKET_ACC_UPLOADS_URL . 'profile-pictures/';
        $this->pp_extensions = ['jpg', 'jpeg', 'png', 'gif'];

        // Display the block
        $this->display_block();
    }

    /**
     * Set error message and type.
     *
     * @param string $type Error type for categorization
     * @param string $message Error message to display
     * @return void
     */
    private function setError(string $type, string $message): void
    {
        $this->error_type = $type;
        $this->error_message = $message;
    }

    /**
     * Display the block.
     *
     * @return void
     */
    protected function display_block()
    {
        // Process the form
        $process_form = $this->process_form();
        $remove_form = $this->remove_form();

        if ($process_form === false || $remove_form === false) {
            $error_args = [
                'error_message' => $this->error_message,
                'error_type' => $this->error_type,
                'pp_max_size' => $this->pp_max_size,
                'pp_extensions' => $this->pp_extensions,
            ];
            $this->blocks->render_template('profile-picture-change_error', $error_args);
        }

        if ($process_form === true) {
            $this->blocks->render_template('profile-picture-change_success');
        }

        // Get user profile picture
        $pp_profile_picture = WACC()->Profile->getProfilePicture();
        $pp_is_custom = WACC()->Profile->isCustomProfilePicture($pp_profile_picture);

        $args = [
            'is_custom'   => $pp_is_custom,
            'pp_url'      => $pp_profile_picture,
            'pp_max_size' => $this->pp_max_size,
        ];

        // Render block
        $this->blocks->render_template('profile-picture-change', $args);
    }

    /**
     * Process the form and save the profile picture.
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
            $this->setError('security', __('Security verification failed. Please try again.', 'wicket-acc'));

            return false;
        }

        // Check if the file is set and valid
        if (!isset($_FILES['profile-image'])) {
            $this->setError('no_file', __('No file was selected for upload.', 'wicket-acc'));

            return false;
        }

        // Check for upload errors
        if ($_FILES['profile-image']['error'] !== UPLOAD_ERR_OK) {
            $upload_error_messages = [
                UPLOAD_ERR_INI_SIZE => __('The uploaded file exceeds the server\'s maximum file size limit.', 'wicket-acc'),
                UPLOAD_ERR_FORM_SIZE => __('The uploaded file exceeds the form\'s maximum file size limit.', 'wicket-acc'),
                UPLOAD_ERR_PARTIAL => __('The file was only partially uploaded. Please try again.', 'wicket-acc'),
                UPLOAD_ERR_NO_FILE => __('No file was uploaded.', 'wicket-acc'),
                UPLOAD_ERR_NO_TMP_DIR => __('Missing temporary folder on server.', 'wicket-acc'),
                UPLOAD_ERR_CANT_WRITE => __('Failed to write file to disk.', 'wicket-acc'),
                UPLOAD_ERR_EXTENSION => __('File upload stopped by extension.', 'wicket-acc'),
            ];
            $error_message = $upload_error_messages[$_FILES['profile-image']['error']] ?? __('Unknown upload error occurred.', 'wicket-acc');
            $this->setError('upload_error', $error_message);

            return false;
        }

        // Check if the file is empty
        if ($_FILES['profile-image']['size'] === 0) {
            $this->setError('empty_file', __('The uploaded file appears to be empty. Please select a valid image file.', 'wicket-acc'));

            return false;
        }

        // Get the extension
        $file_extension = pathinfo($_FILES['profile-image']['name'], PATHINFO_EXTENSION);

        // Check if the file extension is allowed
        if (!in_array(strtolower($file_extension), array_map('strtolower', $this->pp_extensions))) {
            $this->setError('invalid_extension', sprintf(
                __('Invalid file format. Please upload a file with one of these extensions: %s', 'wicket-acc'),
                implode(', ', array_map('strtoupper', $this->pp_extensions))
            ));

            return false;
        }

        // Check if is a valid image
        if (@getimagesize($_FILES['profile-image']['tmp_name']) === false) {
            $this->setError('invalid_image', __('The uploaded file is not a valid image. Please upload a valid image file.', 'wicket-acc'));

            return false;
        }

        // Check if the file size is too big. pp_max_size is in MB
        if ($_FILES['profile-image']['size'] > $this->pp_max_size * 1024 * 1024) { // Convert MB to bytes
            $this->setError('file_too_large', sprintf(
                __('The uploaded file is too large. Maximum file size allowed is %dMB.', 'wicket-acc'),
                $this->pp_max_size
            ));

            return false;
        }

        // User ID
        $user_id = sanitize_text_field(wp_unslash($form['user_id']));

        // Remove any existing file on wicket-profile-pictures/{user_id}.{extension}
        $file_path = $this->pp_uploads_path . $user_id . '.' . $file_extension;

        // Delete the file if it exists
        foreach ($this->pp_extensions as $ext) {
            $other_file_path = $this->pp_uploads_path . $user_id . '.' . $ext;

            if (file_exists($other_file_path)) {
                wp_delete_file($other_file_path);
            }
        }

        // No matter whats the file name, rename it to {user_id}.{extension}
        $_FILES['profile-image']['name'] = $user_id . '.' . $file_extension;
        $_FILES['profile-image']['full_path'] = $user_id . '.' . $file_extension;

        // Create subfolder if it doesn't exist
        if (!file_exists($this->pp_uploads_path)) {
            wp_mkdir_p($this->pp_uploads_path);
        }

        // Move the file to the uploads directory, move_uploaded_file
        $movefile = move_uploaded_file($_FILES['profile-image']['tmp_name'], $file_path);

        if (!$movefile) {
            $this->setError('file_move_error', __('Failed to save the uploaded file. Please try again.', 'wicket-acc'));

            return false;
        }

        // Crop to square
        $file_path_crop = $this->crop_center_of_rectangle_from_file($file_path, $file_path);

        // Check for errors
        if ($file_path_crop === false) {
            @unlink($file_path);
            @unlink($file_path_crop);
            $this->setError('crop_error', __('Failed to process the image. Please try uploading a different image.', 'wicket-acc'));

            return false;
        }

        // Replace with cropped file
        unlink($file_path);

        $movefile = rename($file_path_crop, $file_path);

        // Check for errors
        if (!$movefile) {
            $this->setError('file_replace_error', __('Failed to finalize the image processing. Please try again.', 'wicket-acc'));

            return false;
        }

        return true;
    }

    /**
     * Process the remove form and delete the profile picture.
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
            $this->setError('security', __('Security verification failed. Please try again.', 'wicket-acc'));

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
     * Crop square from the center of an image file.
     *
     * @param string $src_file    Path to the source image file.
     * @param string $destination_file    Path to save the cropped image.
     * @return string|false       Path to the cropped image file on success, false on failure.
     */
    protected function crop_center_of_rectangle_from_file($src_file, $destination_file)
    {
        [$src_width, $src_height] = getimagesize($src_file);

        // Could we get the dimensions?
        if ($src_width === false || $src_height === false) {
            return false;
        }

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
            include ABSPATH . 'wp-admin/includes/image.php';
        }

        // Crop the image using wp_crop_image
        $cropped_file = wp_crop_image($src_file, $crop_x, $crop_y, $crop_size, $crop_size, $crop_size, $crop_size, false, $destination_file);

        if (is_wp_error($cropped_file) || $cropped_file === false) {
            return false;
        }

        return $cropped_file;
    }
}
