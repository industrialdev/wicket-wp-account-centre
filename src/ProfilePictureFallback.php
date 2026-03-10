<?php

namespace WicketAcc;

// No direct access
defined('ABSPATH') || exit;

/**
 * Redirects legacy numeric profile-picture URLs to UUID-based filenames.
 */
class ProfilePictureFallback extends WicketAcc
{
    /**
     * Constructor.
     */
    public function __construct(
        protected array $extensions = ['jpg', 'jpeg', 'png', 'gif'],
        protected string $uploads_path = WICKET_ACC_UPLOADS_PATH . 'profile-pictures/',
        protected string $uploads_url = WICKET_ACC_UPLOADS_URL . 'profile-pictures/'
    ) {
        add_action('template_redirect', [$this, 'maybeRedirectLegacyProfilePictureRequest'], 0);
    }

    /**
     * Redirect legacy numeric profile-picture URLs to UUID-based filenames when needed.
     *
     * Only runs for 404 requests under the profile-pictures uploads path.
     *
     * @return void
     */
    public function maybeRedirectLegacyProfilePictureRequest(): void
    {
        if (!is_404()) {
            return;
        }

        $request_path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
        if (!is_string($request_path) || $request_path === '') {
            return;
        }

        $matches = [];
        $matched = preg_match(
            '#/wp-content/uploads/wicket-account-center/profile-pictures/([0-9]+)\.(jpg|jpeg|png|gif)$#i',
            $request_path,
            $matches
        );

        if ($matched !== 1) {
            return;
        }

        $user_id = absint($matches[1]);
        if ($user_id <= 0) {
            return;
        }

        $user = get_user_by('id', $user_id);
        if (!$user instanceof \WP_User || $user->user_login === '') {
            return;
        }

        $person_uuid = sanitize_file_name((string) $user->user_login);
        if ($person_uuid === '' || ctype_digit($person_uuid)) {
            return;
        }

        $requested_extension = strtolower($matches[2]);
        $extensions = array_values(array_unique([
            $requested_extension,
            ...$this->extensions,
        ]));

        foreach ($extensions as $extension) {
            $candidate_path = $this->uploads_path . $person_uuid . '.' . $extension;

            if (!file_exists($candidate_path)) {
                continue;
            }

            wp_safe_redirect(
                $this->uploads_url . rawurlencode($person_uuid) . '.' . $extension,
                302,
                'Wicket ACC Profile Picture UUID Fallback'
            );
            exit;
        }
    }
}
