<?php

namespace WicketAcc\Blocks\OrgLogo;

use WicketAcc\Blocks;

// No direct access
defined('ABSPATH') || exit;

/**
 * Wicket Organization Profile Picture Block.
 *
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
        protected array $pp_extensions = []
    ) {
        $this->block = $block;
        $this->is_preview = $is_preview;
        $this->blocks = $blocks ?? new Blocks();

        $this->uploads_path = WICKET_ACC_UPLOADS_PATH . 'organization-logos/';
        $this->uploads_url = WICKET_ACC_UPLOADS_URL . 'organization-logos/';
        $this->pp_extensions = ['jpg', 'jpeg', 'png', 'gif'];

        // Check if org_id is set on superglobals for not showing the block on organization selection screen
        if (!isset($_GET['org_id'])) {
            return;
        }

        $org_id = (isset($_GET['org_id'])) ? $_GET['org_id'] : '';
        $person = wicket_current_person();
        $org_ids = [];
        // figure out orgs I should see
        // this association to the org is set on each role. The actual role types we look at might change depending on the project
        foreach ($person->included() as $person_included) {
            if (isset($person_included['attributes']['name'])) {
                if ($person_included['type'] == 'roles' && (stristr($person_included['attributes']['name'], 'org_editor'))) {
                    if (isset($person_included['relationships']['resource']['data']['id']) && $person_included['relationships']['resource']['data']['type'] == 'organizations') {
                        $org_ids[] = $person_included['relationships']['resource']['data']['id'];
                    }
                }
            }
        }

        // If the org_id is not in the org_ids array, return
        if (!in_array($org_id, $org_ids, true)) {
            return;
        }

        // Display the block
        $this->display_block();
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
        $org_id = $_GET['org_id'];

        if ($process_form === false) {
            $this->blocks->render_template('organization-logo-change_error');
        }

        if ($process_form === true) {
            $this->blocks->render_template('organization-logo-change_success');
        }

        // Get user profile picture
        $organiation_logo = WACC()->OrganizationProfile->get_organization_logo($org_id);

        $args = [
            'organization_logo_url' => $organiation_logo,
            'max_upload_size'       => $this->max_size,
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
            return false;
        }

        // Check if the file is set and valid
        if (!isset($_FILES['org-logo']) || $_FILES['org-logo']['error'] !== UPLOAD_ERR_OK) {
            return false;
        }

        // Get the extension
        $file_extension = pathinfo($_FILES['org-logo']['name'], PATHINFO_EXTENSION);

        // Check if is a valid image
        if (@getimagesize($_FILES['org-logo']['tmp_name']) === false) {
            return false;
        }

        // Check if the file size is too big. max_size is in MB
        if ($_FILES['org-logo']['size'] > $this->max_size * 1024 * 1024) { // Convert MB to bytes
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
