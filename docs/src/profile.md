# ACC Profile Class Documentation

## Overview
The `Profile` class is responsible for managing user profile pictures within the Wicket Account Centre. It overrides WordPress's default avatar functionality (`get_avatar` and `get_avatar_url`) to use locally stored, custom profile pictures instead of Gravatar.

## Class Definition
```php
namespace WicketAcc;

class Profile extends WicketAcc
{
    /**
     * Constructor.
     *
     * @param array $pp_extensions Default: ['jpg', 'jpeg', 'png', 'gif']
     * @param string $pp_uploads_path Default: WICKET_ACC_UPLOADS_PATH . 'profile-pictures/'
     * @param string $pp_uploads_url Default: WICKET_ACC_UPLOADS_URL . 'profile-pictures/'
     */
    public function __construct(
        protected array $pp_extensions = ['jpg', 'jpeg', 'png', 'gif'],
        protected string $pp_uploads_path = WICKET_ACC_UPLOADS_PATH . 'profile-pictures/',
        protected string $pp_uploads_url = WICKET_ACC_UPLOADS_URL . 'profile-pictures/'
    );

    /**
     * Get the profile picture URL.
     *
     * @param int $user_id Optional user ID.
     * @return string|bool Profile picture URL (custom, ACF option, or plugin default SVG), or false if the user ID is invalid.
     */
    public function getProfilePicture($user_id = null);

    /**
     * Check if the profile picture is a custom one.
     *
     * @param string $pp_profile_picture
     * @return bool True if the profile picture is a custom one.
     */
    public function isCustomProfilePicture($pp_profile_picture);
}
```

## Core Functionality

### Avatar Integration
The `__construct` method hooks `get_wicket_avatar` into WordPress's `get_avatar` filter and `get_wicket_avatar_url` into the `get_avatar_url` filter, both with a priority of 2050. These handler methods then utilize `getProfilePicture()` (which attempts to retrieve a custom uploaded picture, then an ACF option default, then a plugin default SVG) to return the appropriate avatar `<img>` tag or URL, effectively overriding WordPress's default Gravatar behavior.

### Profile Picture Retrieval
- `getProfilePicture()`: This is the main public method for fetching a user's profile picture URL. It checks for an uploaded image (e.g., `.jpg`, `.png`) corresponding to the user's ID. If a custom image isn't found, it falls back to a default image specified in the plugin settings or a default SVG image included with the plugin.
- `isCustomProfilePicture()`: A helper method to determine if a given URL points to a custom-uploaded picture or one of the default fallbacks.

## Usage Example
The `Profile` class is accessible via the `WACC()` global function.

```php
// Get the profile picture URL for the current user
$profile_pic_url = WACC()->profile()->getProfilePicture();

if ($profile_pic_url) {
    echo '<img src="' . esc_url($profile_pic_url) . '" alt="Profile Picture">';
}

// Get the profile picture for a specific user (ID 42)
$user_profile_pic = WACC()->profile()->getProfilePicture(42);
```

## Error Handling
- If `getProfilePicture()` is called and the determined `$user_id` (either passed or from `get_current_user_id()`) results in a `WP_Error` object (which is atypical for `get_current_user_id()`), it returns `false`. For a standard logged-out user (where `get_current_user_id()` returns `0`), the method will attempt to find `0.jpg` etc., then check the ACF default, and finally return the plugin's default SVG, not `false`.
- If no custom or default image is found, it returns a path to a fallback SVG image, ensuring an image is always displayed.
