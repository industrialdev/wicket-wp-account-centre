<?php

namespace WicketAcc;

// No direct access
defined('ABSPATH') || exit;

/**
 * Organization Profile for Wicket Account Centre.
 *
 * Manage all actions of organizations's profile on WordPress.
 */
class OrganizationProfile extends WicketAcc
{
    /**
     * Constructor.
     */
    public function __construct(
        protected array $extensions = ['jpg', 'jpeg', 'png', 'gif'],
        protected string $uploads_path = WICKET_ACC_UPLOADS_PATH . 'organization-logos/',
        protected string $uploads_url = WICKET_ACC_UPLOADS_URL . 'organization-logos/'
    ) {}

    /**
     * Get the organization logo URL.
     *
     * @param int $org_id Organization ID.
     *
     * @return string|bool Organization logo URL, default one or false on error
     */
    public function get_organization_logo($org_id = null)
    {
        if (empty($org_id)) {
            return false;
        }

        // Check for jpg or png
        $extensions = $this->extensions;
        $org_logo = '';
        $valid_extension = '';

        foreach ($extensions as $ext) {
            $file_path = $this->uploads_path . $org_id . '.' . $ext;

            if (file_exists($file_path)) {
                // Found it!
                $org_logo = $file_path;
                $valid_extension = $ext;
                break;
            }
        }

        // Get file URL
        if (!empty($valid_extension)) {
            $org_logo = $this->uploads_url . $org_id . '.' . $valid_extension;
        }

        // Still no image? Return the default svg
        if (empty($org_logo)) {
            $org_logo = '';
        }

        return $org_logo;
    }
}
