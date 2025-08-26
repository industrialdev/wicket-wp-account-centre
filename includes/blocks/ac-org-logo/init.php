<?php

namespace WicketAcc\Blocks\OrgLogo;

use WicketAcc\Blocks;

// No direct access
defined('ABSPATH') || exit;

/**
 * Wicket Organization Profile Picture Block.
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
        protected int $max_size = 1,
        protected string $uploads_path = '',
        protected string $uploads_url = '',
        protected array $pp_extensions = [],
        protected ?string $error_message = null,
        protected ?string $error_type = null
    ) {
        $this->block = $block;
        $this->is_preview = $is_preview;
        $this->blocks = $blocks ?? new Blocks();

        $this->uploads_path = WICKET_ACC_UPLOADS_PATH . 'organization-logos/';
        $this->uploads_url = WICKET_ACC_UPLOADS_URL . 'organization-logos/';
        $this->pp_extensions = ['jpg', 'jpeg', 'png', 'gif'];

        // Get max size from centralized helper (CF preferred, ACF fallback)
        $this->max_size = absint(WACC()->getOption('acc_profile_picture_size', 1));

        // Ensure we have a valid max size (minimum 1MB)
        $this->max_size = max(1, $this->max_size);

        $org_id = (isset($_GET['org_id'])) ? $_GET['org_id'] : '';
        $child_org_id = (isset($_GET['child_org_id'])) ? $_GET['child_org_id'] : '';

        // Child organization compatibility
        if (!empty($child_org_id)) {
            $parent_org_id = $org_id;
            $org_id = $child_org_id;
        }

        $person = wicket_current_person();
        $org_ids = [];

        // Figure out orgs I should see this association to the org is set on each role. The actual role types we look at might change depending on the project
        foreach ($person->included() as $person_included) {
            if (isset($person_included['attributes']['name'])) {
                if ($person_included['type'] == 'roles' && (stristr($person_included['attributes']['name'], 'org_editor'))) {
                    if (isset($person_included['relationships']['resource']['data']['id']) && $person_included['relationships']['resource']['data']['type'] == 'organizations') {
                        $org_ids[] = $person_included['relationships']['resource']['data']['id'];
                    }
                }
            }
        }

        // If org_ids only has one org, set the org_id to that org
        if (count($org_ids) === 1) {
            $org_id = $org_ids[0];
        }

        // If the org_id is not in the org_ids array, return. But let user pass if a child org is defined
        if (!in_array($org_id, $org_ids, true) && empty($child_org_id)) {
            return;
        }

        // Display the block
        $this->display_block($org_id);
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
    protected function display_block($org_id)
    {
        // Process the form
        $process_form = $this->process_form();

        $child_org_id = $_GET['child_org_id'] ?? '';

        // Child organization compatibility
        if (!empty($child_org_id)) {
            $parent_org_id = $org_id;
            $org_id = $child_org_id;
        }

        if ($process_form === false) {
            $error_args = [
                'error_message' => $this->error_message,
                'error_type' => $this->error_type,
                'max_size' => $this->max_size,
                'pp_extensions' => $this->pp_extensions,
            ];
            $this->blocks->render_template('organization-logo-change_error', $error_args);
        }

        if ($process_form === true) {
            $this->blocks->render_template('organization-logo-change_success');
        }

        // Get user profile picture

        $organiation_logo = WACC()->OrganizationProfile()->get_organization_logo($org_id);

        $args = [
            'organization_logo_url' => $organiation_logo,
            'max_upload_size'       => $this->max_size,
            'org_id'                => $org_id,
        ];

        // Render block
        $this->blocks->render_template('organization-logo-change', $args);
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
        if (!isset($_POST['action']) || $_POST['action'] !== 'wicket-acc-org-logo-form') {
            return;
        }

        $form = $_POST;

        // Check nonce
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($form['nonce'])), 'wicket-acc-org-logo-form')) {
            $this->setError('security', __('Security verification failed. Please try again.', 'wicket-acc'));

            return false;
        }

        // Check if the file is set and valid
        if (!isset($_FILES['org-logo'])) {
            $this->setError('no_file', __('No file was selected for upload.', 'wicket-acc'));

            return false;
        }

        // Check for upload errors
        if ($_FILES['org-logo']['error'] !== UPLOAD_ERR_OK) {
            $upload_error_messages = [
                UPLOAD_ERR_INI_SIZE => __('The uploaded file exceeds the server\'s maximum file size limit.', 'wicket-acc'),
                UPLOAD_ERR_FORM_SIZE => __('The uploaded file exceeds the form\'s maximum file size limit.', 'wicket-acc'),
                UPLOAD_ERR_PARTIAL => __('The file was only partially uploaded. Please try again.', 'wicket-acc'),
                UPLOAD_ERR_NO_FILE => __('No file was uploaded.', 'wicket-acc'),
                UPLOAD_ERR_NO_TMP_DIR => __('Missing temporary folder on server.', 'wicket-acc'),
                UPLOAD_ERR_CANT_WRITE => __('Failed to write file to disk.', 'wicket-acc'),
                UPLOAD_ERR_EXTENSION => __('File upload stopped by extension.', 'wicket-acc'),
            ];
            $error_message = $upload_error_messages[$_FILES['org-logo']['error']] ?? __('Unknown upload error occurred.', 'wicket-acc');
            $this->setError('upload_error', $error_message);

            return false;
        }

        // Check if the file is empty
        if ($_FILES['org-logo']['size'] === 0) {
            $this->setError('empty_file', __('The uploaded file appears to be empty. Please select a valid image file.', 'wicket-acc'));

            return false;
        }

        // Get the extension
        $file_extension = pathinfo($_FILES['org-logo']['name'], PATHINFO_EXTENSION);

        // Check if the file extension is allowed
        if (!in_array(strtolower($file_extension), array_map('strtolower', $this->pp_extensions))) {
            $this->setError('invalid_extension', sprintf(
                __('Invalid file format. Please upload a file with one of these extensions: %s', 'wicket-acc'),
                implode(', ', array_map('strtoupper', $this->pp_extensions))
            ));

            return false;
        }

        // Check if is a valid image
        if (@getimagesize($_FILES['org-logo']['tmp_name']) === false) {
            $this->setError('invalid_image', __('The uploaded file is not a valid image. Please upload a valid image file.', 'wicket-acc'));

            return false;
        }

        // Check if the file size is too big. max_size is in MB
        if ($_FILES['org-logo']['size'] > $this->max_size * 1024 * 1024) { // Convert MB to bytes
            $this->setError('file_too_large', sprintf(
                __('The uploaded file is too large. Maximum file size allowed is %dMB.', 'wicket-acc'),
                $this->max_size
            ));

            return false;
        }

        // Org ID
        $org_id = sanitize_text_field(wp_unslash($form['org_id']));

        // Remove any existing file on wicket-profile-pictures/{user_id}.{extension}
        $file_path = $this->uploads_path . $org_id . '.' . $file_extension;

        // Delete the file if it exists
        foreach ($this->pp_extensions as $ext) {
            $other_file_path = $this->uploads_path . $org_id . '.' . $ext;

            if (file_exists($other_file_path)) {
                wp_delete_file($other_file_path);
            }
        }

        // No matter whats the file name, rename it to {user_id}.{extension}
        $_FILES['org-logo']['name'] = $org_id . '.' . $file_extension;
        $_FILES['org-logo']['full_path'] = $org_id . '.' . $file_extension;

        // Create subfolder if it doesn't exist
        if (!file_exists($this->uploads_path)) {
            wp_mkdir_p($this->uploads_path);
        }

        // Move the file to the uploads directory, move_uploaded_file
        $movefile = move_uploaded_file($_FILES['org-logo']['tmp_name'], $file_path);

        // Check for errors
        if (!$movefile) {
            $this->setError('file_move_error', __('Failed to save the uploaded file. Please try again.', 'wicket-acc'));

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
        if (!isset($_POST['action']) || $_POST['action'] !== 'wicket-acc-org-profile-picture-remove-form') {
            return;
        }

        $form = $_POST;

        // Check nonce
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($form['nonce'])), 'wicket-acc-org-profile-picture-remove-form')) {
            $this->setError('security', __('Security verification failed. Please try again.', 'wicket-acc'));

            return false;
        }

        // Org ID
        $org_id = $form['org_id'];

        // Remove any existing file on wicket-profile-pictures/{user_id}.{extension}
        foreach ($this->pp_extensions as $ext) {
            $file_path = $this->uploads_path . $org_id . '.' . $ext;

            if (file_exists($file_path)) {
                wp_delete_file($file_path);
            }
        }

        return true;
    }
}
