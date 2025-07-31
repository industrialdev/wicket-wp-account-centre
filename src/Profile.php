<?php

namespace WicketAcc;

// No direct access
defined('ABSPATH') || exit;

/**
 * Profile for Wicket Account Centre.
 *
 * Manage all actions of user's profile on WordPress.
 */
class Profile extends WicketAcc
{
    /**
     * Constructor.
     */
    public function __construct(
        protected array $pp_extensions = ['jpg', 'jpeg', 'png', 'gif'],
        protected string $pp_uploads_path = WICKET_ACC_UPLOADS_PATH . 'profile-pictures/',
        protected string $pp_uploads_url = WICKET_ACC_UPLOADS_URL . 'profile-pictures/'
    ) {
        add_filter('get_avatar', [$this, 'get_wicket_avatar'], 2050, 5);
        add_filter('get_avatar_url', [$this, 'get_wicket_avatar_url'], 2050, 3);
    }

    /**
     * Changes default WP get_avatar behavior.
     *
     * @param string $avatar
     * @param mixed $id_or_email
     * @param int $size
     * @param string $default
     * @param string $alt
     *
     * @return string
     */
    public function get_wicket_avatar(string $avatar, $id_or_email, int $size, string $default, string $alt): string
    {
        // Get the user ID from the id_or_email parameter
        $user_id = null;

        if (is_numeric($id_or_email)) {
            $user_id = (int) $id_or_email;
        } elseif ($id_or_email instanceof \WP_User) {
            // Handle WP_User object
            $user_id = $id_or_email->ID;
        } else {
            $user = get_user_by('email', $id_or_email);
            if ($user) {
                $user_id = $user->ID;
            }
        }

        // Get the profile picture URL
        $pp_profile_picture = $this->getProfilePicture($user_id);

        // If the profile picture URL is not empty, return it
        if (!empty($pp_profile_picture)) {
            $avatar = "<img src='$pp_profile_picture' alt='$alt' class='avatar avatar-$size photo' height='$size' width='$size' />";
        }

        return $avatar;
    }

    /**
     * Changes default WP get_avatar_url behavior.
     *
     * @param string $avatar_url
     * @param mixed $id_or_email
     * @param array $args
     *
     * @return string
     */
    public function get_wicket_avatar_url(string $avatar_url, $id_or_email, array $args = []): string
    {
        // Get the user ID from the id_or_email parameter
        $user_id = null;

        if (is_numeric($id_or_email)) {
            $user_id = (int) $id_or_email;
        } elseif ($id_or_email instanceof \WP_User) {
            // Handle WP_User object
            $user_id = $id_or_email->ID;
        } else {
            $user = get_user_by('email', $id_or_email);
            if ($user) {
                $user_id = $user->ID;
            }
        }

        // Get the profile picture URL
        $pp_profile_picture = $this->getProfilePicture($user_id);

        // If the profile picture URL is not empty, return it
        if (!empty($pp_profile_picture)) {
            $avatar_url = $pp_profile_picture;
        }

        return $avatar_url;
    }

    /**
     * Get the profile picture URL.
     *
     * @param int $user_id Optional user ID. If not provided, the current user ID will be used.
     *
     * @return string|bool Profile picture URL, default one or false on error
     */
    public function getProfilePicture(?int $user_id = null): string|false
    {

        // If no user ID is provided, use the current user ID
        switch (true) {
            case $user_id === null :
                $user_id = get_current_user_id();
                break;
            case is_numeric($user_id) && intval($user_id) > 0 :
                $user_id = intval($user_id);
                break;
            default:
                $user_id = 0;
        }

        // Check for jpg, jpeg, png, or gif
        $extensions = $this->pp_extensions;
        $pp_profile_picture = '';

        foreach ($extensions as $ext) {
            $file_path = $this->pp_uploads_path . $user_id . '.' . $ext;

            if (file_exists($file_path)) {
                // Found it!
                $pp_profile_picture = $this->pp_uploads_url . $user_id . '.' . $ext;
                break;
            }
        }

        // Check if ACC option acc_profile_picture_default has an image URL set
        if (empty($pp_profile_picture)) {
            // Get from Carbon Fields with fallback to ACF
            if (function_exists('carbon_get_theme_option')) {
                $default_picture = wp_get_attachment_url(carbon_get_theme_option('acc_profile_picture_default'));
            } else {
                $default_picture = get_field('acc_profile_picture_default', 'option');
            }
            if (!empty($default_picture)) {
                $pp_profile_picture = $default_picture;
            }
        }

        // Still no image? Return the default svg
        if (empty($pp_profile_picture)) {
            $pp_profile_picture = WICKET_ACC_URL . '/assets/images/profile-picture-default.svg';
        }

        return $pp_profile_picture;
    }

    /**
     * Check if the profile picture is a custom one.
     *
     * @param string $pp_profile_picture
     *
     * @return bool True if the profile picture is a custom one, false if it is the default one
     */
    public function isCustomProfilePicture(string $pp_profile_picture): bool
    {
        $pp_profile_picture_plugin = WICKET_ACC_URL . '/assets/images/profile-picture-default.svg';
        // Get from Carbon Fields with fallback to ACF
        if (function_exists('carbon_get_theme_option')) {
            $pp_profile_picture_override =  wp_get_attachment_url(carbon_get_theme_option('acc_profile_picture_default'));
        } else {
            $pp_profile_picture_override = get_field('acc_profile_picture_default', 'option');
        }

        // Check if $pp_profile_picture is one of the two defaults
        if (empty($pp_profile_picture_override)) {
            return $pp_profile_picture !== $pp_profile_picture_plugin;
        }

        return $pp_profile_picture !== $pp_profile_picture_plugin && $pp_profile_picture !== $pp_profile_picture_override;
    }
}
