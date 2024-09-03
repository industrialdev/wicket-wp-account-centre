<?php

namespace WicketAcc;

// No direct access
defined('ABSPATH') || exit;

/**
 * Profile for Wicket Account Centre
 *
 * Manage all actions of user's profile on WordPress.
 */
class Profile extends WicketAcc
{
	/**
	 * Constructor.
	 */
	public function __construct(
		protected array $pp_extensions    = ['jpg', 'jpeg', 'png', 'gif'],
		protected string $pp_uploads_path = WICKET_ACC_UPLOADS_PATH . 'profile-pictures/',
		protected string $pp_uploads_url  = WICKET_ACC_UPLOADS_URL . 'profile-pictures/'
	) {
		add_filter('get_avatar', [$this, 'get_wicket_avatar'], 2050, 5);
		add_filter('get_avatar_url', [$this, 'get_wicket_avatar_url'], 2050, 3);
	}

	/**
	 * Changes default WP get_avatar behavior
	 *
	 * @param string $avatar
	 * @param mixed $id_or_email
	 * @param int $size
	 * @param string $default
	 * @param string $alt
	 *
	 * @return string
	 */
	public function get_wicket_avatar($avatar, $id_or_email, $size, $default, $alt)
	{
		// Get the profile picture URL
		$pp_profile_picture = $this->get_profile_picture();

		// If the profile picture URL is not empty, return it
		if (!empty($pp_profile_picture)) {
			$avatar = "<img src='$pp_profile_picture' alt='$alt' class='avatar avatar-$size photo' height='$size' width='$size' />";
		}

		return $avatar;
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
	public function get_wicket_avatar_url($avatar_url, $id_or_email, $args = [])
	{
		// Get the profile picture URL
		$pp_profile_picture = $this->get_profile_picture();

		// If the profile picture URL is not empty, return it
		if (!empty($pp_profile_picture)) {
			$avatar_url = $pp_profile_picture;
		}

		return $avatar_url;
	}

	/**
	 * Get the profile picture URL
	 *
	 * @param int $user_id Optional user ID. If not provided, the current user ID will be used.
	 *
	 * @return string|bool Profile picture URL, default one or false on error
	 */
	public function get_profile_picture($user_id = null)
	{
		if (empty($user_id)) {
			// Get current WP user ID
			$user_id = get_current_user_id();
		}

		// Guest?
		if (is_wp_error($user_id)) {
			return false;
		}

		// Check for jpg or png
		$extensions = $this->pp_extensions;
		$pp_profile_picture = '';
		$pp_valid_extension = '';

		foreach ($extensions as $ext) {
			$file_path = $this->pp_uploads_path . $user_id . '.' . $ext;

			if (file_exists($file_path)) {
				// Found it!
				$pp_profile_picture = $file_path;
				$pp_valid_extension = $ext;
				break;
			}
		}

		// Get file URL
		if (!empty($pp_valid_extension)) {
			$pp_profile_picture = $this->pp_uploads_url . $user_id . '.' . $pp_valid_extension;
		}

		// Check if ACC option acc_profile_picture_default has an image URL set
		if (empty($pp_profile_picture) && get_field('acc_profile_picture_default', 'option') !== '') {
			$pp_profile_picture = get_field('acc_profile_picture_default', 'option');
		}

		// Still no image? Return the default svg
		if (empty($pp_profile_picture)) {
			$pp_profile_picture = WICKET_ACC_URL . '/assets/images/profile-picture-default.svg';
		}

		return $pp_profile_picture;
	}

	/**
	 * Check if the profile picture is a custom one
	 *
	 * @param string $pp_profile_picture
	 *
	 * @return bool True if the profile picture is a custom one, false if it is the default one
	 */
	public function is_custom_profile_picture($pp_profile_picture)
	{
		$pp_profile_picture_plugin   = WICKET_ACC_URL . '/assets/images/profile-picture-default.svg';
		$pp_profile_picture_override = get_field('acc_profile_picture_default', 'option');

		// Check if $pp_profile_picture is one of the two
		return $pp_profile_picture !== $pp_profile_picture_plugin && $pp_profile_picture !== $pp_profile_picture_override;
	}
}
