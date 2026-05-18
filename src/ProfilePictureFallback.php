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
        protected array $extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'],
        protected string $uploads_path = WICKET_ACC_UPLOADS_PATH . 'profile-pictures/',
        protected string $uploads_url = WICKET_ACC_UPLOADS_URL . 'profile-pictures/'
    ) {
        add_action('template_redirect', [$this, 'maybeRedirectLegacyProfilePictureRequest'], 0);
    }

    /**
     * Redirect legacy numeric profile-picture URLs to UUID-based filenames when needed.
     * Falls back to the configured default profile picture when no file is found for
     * either the numeric WordPress ID or the UUID identifier.
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

        // Derive the uploads path prefix dynamically to support custom WP_CONTENT_URL,
        // reverse proxies, and multisite path shapes.
        $uploads_path_prefix = rtrim((string) parse_url($this->uploads_url, PHP_URL_PATH), '/');
        if ($uploads_path_prefix === '') {
            return;
        }

        $ext_pattern = implode('|', array_map('preg_quote', $this->extensions));
        $matched = preg_match(
            '#^' . preg_quote($uploads_path_prefix, '#') . '/([^/]+)\.(' . $ext_pattern . ')$#i',
            $request_path,
            $matches
        );

        if ($matched !== 1) {
            return;
        }

        $requested_extension = strtolower($matches[2]);
        $extensions = array_values(array_unique([
            $requested_extension,
            ...$this->extensions,
        ]));

        $identifier = $matches[1];

        // Resolve both identifiers: UUID (user_login) and numeric WP ID.
        $person_uuid = null;
        $wp_user_id = null;

        if (ctype_digit($identifier)) {
            $wp_user_id = absint($identifier);
            $user = $wp_user_id > 0 ? get_user_by('id', $wp_user_id) : false;
            if ($user instanceof \WP_User && $user->user_login !== '') {
                $candidate = sanitize_file_name((string) $user->user_login);
                if ($candidate !== '' && !ctype_digit($candidate)) {
                    $person_uuid = $candidate;
                }
            }
        } else {
            $person_uuid = $identifier;
            $user = get_user_by('login', $identifier);
            if ($user instanceof \WP_User) {
                $wp_user_id = $user->ID > 0 ? $user->ID : null;
            }
        }

        // Check UUID-named file first, then WP-ID-named file.
        $candidates_to_try = array_filter([
            $person_uuid,
            $wp_user_id !== null ? (string) $wp_user_id : null,
        ]);

        foreach ($candidates_to_try as $name) {
            foreach ($extensions as $extension) {
                $candidate_path = $this->uploads_path . $name . '.' . $extension;

                if (!file_exists($candidate_path)) {
                    continue;
                }

                wp_safe_redirect(
                    $this->uploads_url . rawurlencode($name) . '.' . $extension,
                    302,
                    'Wicket ACC Profile Picture Fallback'
                );
                exit;
            }
        }

        // No file found for either identifier — redirect to the configured default.
        $this->redirectToDefaultProfilePicture($request_path);
    }

    /**
     * Redirect to the configured default profile picture.
     *
     * Uses the ACC option image when set, otherwise the plugin's built-in SVG.
     * Loop guard: if the current request is already the configured default URL,
     * skip it and go straight to the built-in SVG to avoid an infinite redirect.
     *
     * @param string $current_request_path The current request path (loop guard input).
     * @return void
     */
    private function redirectToDefaultProfilePicture(string $current_request_path): void
    {
        $default_url = '';
        $wacc = WACC();
        if (is_object($wacc) && method_exists($wacc, 'getAttachmentUrlFromOption')) {
            $default_url = (string) $wacc->getAttachmentUrlFromOption('acc_profile_picture_default', '');
        }

        // Loop guard: if the configured default would resolve to the same path we are
        // already on (i.e. it is also a 404), skip it and use the built-in SVG instead.
        if (!empty($default_url)) {
            $default_path = (string) parse_url($default_url, PHP_URL_PATH);
            if ($default_path === $current_request_path) {
                $default_url = '';
            }
        }

        if (empty($default_url)) {
            $default_url = WICKET_ACC_URL . '/assets/images/profile-picture-default.svg';
        }

        // wp_redirect() is used intentionally here — the destination is a trusted,
        // admin-configured option value and may legitimately point to a CDN or
        // external media host that wp_safe_redirect() would block.
        wp_redirect($default_url, 302, 'Wicket ACC Profile Picture Default Fallback'); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
        exit;
    }
}
