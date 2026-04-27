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
     * Normalize a profile picture extension to lowercase.
     *
     * @param string $filename_or_extension
     *
     * @return string
     */
    public function normalizeProfilePictureExtension(string $filename_or_extension): string
    {
        $value = trim($filename_or_extension);
        if ($value === '') {
            return '';
        }

        $extension = pathinfo($value, PATHINFO_EXTENSION);
        if (!is_string($extension) || $extension === '') {
            $extension = $value;
        }

        return strtolower((string) $extension);
    }

    /**
     * Build normalized upload filename metadata for a profile picture.
     *
     * @param string $original_filename
     * @param string $file_owner
     *
     * @return array{extension:string,filename:string}
     */
    public function buildProfilePictureUploadMeta(string $original_filename, string $file_owner): array
    {
        $extension = $this->normalizeProfilePictureExtension($original_filename);
        $filename = strtolower($file_owner . '.' . $extension);

        return [
            'extension' => $extension,
            'filename' => $filename,
        ];
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
        } elseif (is_string($id_or_email)) {
            // Handle email string
            $user = get_user_by('email', $id_or_email);
            if ($user) {
                $user_id = $user->ID;
            }
        } elseif ($id_or_email instanceof \WP_Comment) {
            // Handle WP_Comment object
            $user_id = (int) $id_or_email->user_id;
        } elseif ($id_or_email instanceof \WP_Post) {
            // Handle WP_Post object
            $user_id = (int) $id_or_email->post_author;
        }

        // Get the profile picture URL
        $pp_profile_picture = $this->getProfilePicture($user_id);

        // If the profile picture URL is not empty, return it
        if (!empty($pp_profile_picture)) {
            $avatar = "<img src='" . esc_url($pp_profile_picture) . "' alt='" . esc_attr($alt) . "' class='avatar avatar-" . (int) $size . " photo' height='" . (int) $size . "' width='" . (int) $size . "' />";
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
        } elseif (is_string($id_or_email)) {
            // Handle email string
            $user = get_user_by('email', $id_or_email);
            if ($user) {
                $user_id = $user->ID;
            }
        } elseif ($id_or_email instanceof \WP_Comment) {
            // Handle WP_Comment object
            $user_id = (int) $id_or_email->user_id;
        } elseif ($id_or_email instanceof \WP_Post) {
            // Handle WP_Post object
            $user_id = (int) $id_or_email->post_author;
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

        $user = $user_id ? get_user_by('id', $user_id) : null;
        $person_uuid = $user instanceof \WP_User ? $user->user_login : null;

        // Check for jpg, jpeg, png, or gif
        $extensions = $this->pp_extensions;
        $pp_profile_picture = '';

        $identifiers = array_filter(array_unique([
            $person_uuid,
            (string) $user_id,
        ]));

        foreach ($identifiers as $identifier) {
            $match = $this->findProfilePictureFileForIdentifier((string) $identifier, $extensions);
            if (is_array($match) && !empty($match['ext'])) {
                // Found it!
                $pp_profile_picture = $this->pp_uploads_url . $identifier . '.' . $match['ext'];
                break;
            }
        }

        // Check if ACC option acc_profile_picture_default has an image URL set
        if (empty($pp_profile_picture)) {
            $default_picture = '';
            $wacc = WACC();
            if (is_object($wacc) && method_exists($wacc, 'getAttachmentUrlFromOption')) {
                $default_picture = (string) $wacc->getAttachmentUrlFromOption('acc_profile_picture_default', '');
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
        $pp_profile_picture_override = '';
        $wacc = WACC();
        if (is_object($wacc) && method_exists($wacc, 'getAttachmentUrlFromOption')) {
            $pp_profile_picture_override = (string) $wacc->getAttachmentUrlFromOption('acc_profile_picture_default', '');
        }

        // Check if $pp_profile_picture is one of the two defaults
        if (empty($pp_profile_picture_override)) {
            return $pp_profile_picture !== $pp_profile_picture_plugin;
        }

        return $pp_profile_picture !== $pp_profile_picture_plugin && $pp_profile_picture !== $pp_profile_picture_override;
    }

    /**
     * Sync profile image URL into MDP additional fields (photo_link).
     *
     * @param string|null $profile_image_url Updated profile image URL, or null when deleted.
     *
     * @return void
     */
    public function syncProfileImageToMdp(?string $profile_image_url = null): void
    {
        $person_uuid = WACC()->Mdp()->Person()->getCurrentPersonUuid();
        if (empty($person_uuid)) {
            return;
        }

        $photo_link = $this->normalizeProfileImageUrlForMdp($profile_image_url);
        $schema_id = 'urn:uuid:8eb9c1b3-c272-4f38-802e-f6539ee47aa4';
        $fields_to_update = $this->buildProfileImageDataFieldsPayload($person_uuid, $schema_id, $photo_link);
        $result = WACC()->Mdp()->Person()->updatePerson($person_uuid, $fields_to_update);

        // Retry once on version conflict after refetching latest data_fields/version.
        if (empty($result['success']) && $this->isRecordConflictResponse($result)) {
            $fields_to_update = $this->buildProfileImageDataFieldsPayload($person_uuid, $schema_id, $photo_link);
            $result = WACC()->Mdp()->Person()->updatePerson($person_uuid, $fields_to_update);
        }

        if (empty($result['success'])) {
            WACC()->Log()->error('Failed to sync profile image to MDP.', [
                'source' => __CLASS__,
                'person_uuid' => $person_uuid,
                'error' => $result['error'] ?? 'unknown',
                'mdp_response' => $result,
            ]);
        }
    }

    /**
     * Delete profile pictures for all provided identifiers using shared normalization rules.
     *
     * @param array<int, string> $identifiers
     *
     * @return void
     */
    public function deleteProfilePicturesByIdentifiers(array $identifiers): void
    {
        foreach ($identifiers as $identifier) {
            $matches = $this->getProfilePictureCandidatesForIdentifier((string) $identifier, $this->pp_extensions);
            foreach ($matches as $match) {
                if (!empty($match['path']) && is_file($match['path'])) {
                    wp_delete_file($match['path']);
                }
            }
        }
    }

    /**
     * Resolve profile picture file for an identifier, normalizing extension casing.
     *
     * Supports legacy files with uppercase extensions and renames them to lowercase
     * when possible so downstream systems consistently consume normalized filenames.
     *
     * @param string $identifier
     * @param array<int, string> $extensions
     *
     * @return array{path:string,ext:string}|null
     */
    private function findProfilePictureFileForIdentifier(string $identifier, array $extensions): ?array
    {
        $matches = $this->getProfilePictureCandidatesForIdentifier($identifier, $extensions);

        return !empty($matches) ? $matches[0] : null;
    }

    /**
     * Get normalized profile picture file candidates for an identifier.
     *
     * @param string $identifier
     * @param array<int, string> $extensions
     *
     * @return array<int, array{path:string,ext:string}>
     */
    private function getProfilePictureCandidatesForIdentifier(string $identifier, array $extensions): array
    {
        $allowed_extensions = array_values(array_unique(array_map('strtolower', $extensions)));
        $candidates = glob($this->pp_uploads_path . $identifier . '.*');

        if (!is_array($candidates) || empty($candidates)) {
            return [];
        }

        usort($candidates, static function (string $a, string $b): int {
            return (int) (filemtime($b) <=> filemtime($a));
        });

        $normalized_matches = [];
        $seen = [];

        foreach ($candidates as $candidate_path) {
            if (!is_file($candidate_path)) {
                continue;
            }

            $candidate_ext = $this->normalizeProfilePictureExtension($candidate_path);
            if (!in_array($candidate_ext, $allowed_extensions, true)) {
                continue;
            }

            $normalized_path = $this->pp_uploads_path . $identifier . '.' . $candidate_ext;
            if ($candidate_path !== $normalized_path && !file_exists($normalized_path)) {
                @rename($candidate_path, $normalized_path);
            }

            if (!file_exists($normalized_path)) {
                $normalized_path = $candidate_path;
            }

            if (isset($seen[$normalized_path])) {
                continue;
            }

            $seen[$normalized_path] = true;
            $normalized_matches[] = [
                'path' => $normalized_path,
                'ext' => $candidate_ext,
            ];
        }

        return $normalized_matches;
    }

    /**
     * Normalize profile image URL to a complete absolute URL for MDP.
     *
     * Accepts absolute URLs, protocol-relative URLs, filesystem paths, and
     * relative paths under uploads; returns a clean absolute URL or null.
     *
     * @param string|null $profile_image_url
     *
     * @return string|null
     */
    private function normalizeProfileImageUrlForMdp(?string $profile_image_url): ?string
    {
        if ($profile_image_url === null) {
            return null;
        }

        $candidate = trim($profile_image_url);
        if ($candidate === '') {
            return null;
        }

        // Already absolute.
        if (preg_match('#^https?://#i', $candidate) === 1) {
            $sanitized = esc_url_raw($candidate);

            return $sanitized !== '' ? $sanitized : null;
        }

        // Protocol-relative URL.
        if (str_starts_with($candidate, '//')) {
            $absolute = set_url_scheme($candidate, is_ssl() ? 'https' : 'http');
            $sanitized = esc_url_raw($absolute);

            return $sanitized !== '' ? $sanitized : null;
        }

        $uploads = wp_get_upload_dir();
        $baseurl = rtrim((string) ($uploads['baseurl'] ?? ''), '/');

        // Convert filesystem/site paths that include the uploads marker.
        $uploads_marker = '/wp-content/uploads/';
        $uploads_marker_pos = strpos($candidate, $uploads_marker);
        if ($baseurl !== '' && $uploads_marker_pos !== false) {
            $relative_upload_path = substr($candidate, $uploads_marker_pos + strlen($uploads_marker));
            $absolute = $baseurl . '/' . ltrim((string) $relative_upload_path, '/');
            $sanitized = esc_url_raw($absolute);

            return $sanitized !== '' ? $sanitized : null;
        }

        // Convert uploads-relative paths (e.g. wicket-account-center/profile-pictures/...).
        if ($baseurl !== '' && !str_starts_with($candidate, '/')) {
            $absolute = $baseurl . '/' . ltrim($candidate, '/');
            $sanitized = esc_url_raw($absolute);

            return $sanitized !== '' ? $sanitized : null;
        }

        // Fallback to site-relative absolute URL.
        $absolute = str_starts_with($candidate, '/') ? home_url($candidate) : home_url('/' . ltrim($candidate, '/'));
        $sanitized = esc_url_raw($absolute);

        return $sanitized !== '' ? $sanitized : null;
    }

    /**
     * Build a data_fields payload for profile image synchronization.
     *
     * @param string $person_uuid
     * @param string $schema_id
     * @param string|null $photo_link
     *
     * @return array
     */
    private function buildProfileImageDataFieldsPayload(string $person_uuid, string $schema_id, ?string $photo_link): array
    {
        $current_person = WACC()->Mdp()->Person()->getPersonByUuid($person_uuid);
        $current_data_fields = $this->extractPersonDataFields($current_person);
        $data_field = $this->findDataFieldBySchema($current_data_fields, $schema_id);

        if (!is_array($data_field)) {
            if (!empty($current_data_fields)) {
                WACC()->Log()->info('Profile image schema not found in current data_fields; creating new payload field.', [
                    'source' => __CLASS__,
                    'person_uuid' => $person_uuid,
                    'schema_id' => $schema_id,
                    'available_data_field_keys' => $this->getDataFieldShapeSummary($current_data_fields),
                ]);
            }

            $data_field = [
                '$schema' => $schema_id,
                'version' => 0,
                'value' => [],
            ];
        }

        $data_field_value = $data_field['value'] ?? [];
        if (!is_array($data_field_value)) {
            $data_field_value = [];
        }
        $data_field_value['photo_link'] = $photo_link;
        $data_field['value'] = $data_field_value;
        $data_field['version'] = isset($data_field['version']) ? (int) $data_field['version'] : 0;

        if (empty($data_field['$schema']) && empty($data_field['schema'])) {
            $data_field['$schema'] = $schema_id;
        }

        return [
            'attributes' => [
                'data_fields' => [
                    $data_field,
                ],
            ],
        ];
    }

    /**
     * Extract data_fields from known SDK payload shapes.
     *
     * @param object|false $person
     *
     * @return array
     */
    private function extractPersonDataFields(object|false $person): array
    {
        if (!$person) {
            return [];
        }

        // Wicket SDK entities expose attributes via magic getters; use getAttribute when available.
        if (method_exists($person, 'getAttribute')) {
            $entity_data_fields = $person->getAttribute('data_fields');
            if ($entity_data_fields instanceof \Traversable) {
                return iterator_to_array($entity_data_fields);
            }
            if (is_array($entity_data_fields)) {
                return $entity_data_fields;
            }
        }

        if (isset($person->attributes) && is_object($person->attributes)
            && isset($person->attributes->data_fields)) {
            if ($person->attributes->data_fields instanceof \Traversable) {
                return iterator_to_array($person->attributes->data_fields);
            }
            if (is_array($person->attributes->data_fields)) {
                return $person->attributes->data_fields;
            }
        }

        if (isset($person->attributes) && is_array($person->attributes)
            && isset($person->attributes['data_fields'])) {
            if ($person->attributes['data_fields'] instanceof \Traversable) {
                return iterator_to_array($person->attributes['data_fields']);
            }
            if (is_array($person->attributes['data_fields'])) {
                return $person->attributes['data_fields'];
            }
        }

        if (isset($person->data) && is_array($person->data)
            && isset($person->data['attributes']['data_fields'])
        ) {
            if ($person->data['attributes']['data_fields'] instanceof \Traversable) {
                return iterator_to_array($person->data['attributes']['data_fields']);
            }
            if (is_array($person->data['attributes']['data_fields'])) {
                return $person->data['attributes']['data_fields'];
            }
        }

        if (isset($person->data) && is_object($person->data)
            && isset($person->data->attributes) && is_object($person->data->attributes)
            && isset($person->data->attributes->data_fields)) {
            if ($person->data->attributes->data_fields instanceof \Traversable) {
                return iterator_to_array($person->data->attributes->data_fields);
            }
            if (is_array($person->data->attributes->data_fields)) {
                return $person->data->attributes->data_fields;
            }
        }

        // Final fallback for unknown SDK object shapes.
        try {
            $person_array = WACC()->Mdp()->Helper()->convertObjectToArray($person);

            if (isset($person_array['attributes']) && is_array($person_array['attributes'])
                && isset($person_array['attributes']['data_fields'])) {
                $fallback_data_fields = $person_array['attributes']['data_fields'];

                if ($fallback_data_fields instanceof \Traversable) {
                    return iterator_to_array($fallback_data_fields);
                }
                if (is_array($fallback_data_fields)) {
                    return $fallback_data_fields;
                }
            }
        } catch (\Throwable $exception) {
            WACC()->Log()->warning('Unable to normalize person object while extracting data_fields.', [
                'source' => __CLASS__,
                'error' => $exception->getMessage(),
            ]);
        }

        return [];
    }

    /**
     * Find a specific data_field entry by schema.
     *
     * @param array $data_fields
     * @param string $schema_id
     *
     * @return array|null
     */
    private function findDataFieldBySchema(array $data_fields, string $schema_id): ?array
    {
        foreach ($data_fields as $field) {
            $field_array = $this->normalizeDataField($field);
            if (!is_array($field_array)) {
                continue;
            }

            $field_schema = $field_array['$schema'] ?? ($field_array['schema'] ?? ($field_array['schema_id'] ?? ($field_array['schema_uuid'] ?? null)));

            if ($field_schema === $schema_id) {
                return $field_array;
            }
        }

        return null;
    }

    /**
     * Normalize a data_field entry to a plain array.
     *
     * @param mixed $field
     *
     * @return array|null
     */
    private function normalizeDataField(mixed $field): ?array
    {
        if (is_array($field)) {
            return $field;
        }

        if (is_object($field)) {
            if (method_exists($field, 'toArray')) {
                $field_array = $field->toArray();
                if (is_array($field_array)) {
                    return $field_array;
                }
            }

            $field_array = (array) $field;
            if (!empty($field_array)) {
                return $field_array;
            }
        }

        return null;
    }

    /**
     * Summarize available data_field entry keys for debugging shape mismatches.
     *
     * @param array $data_fields
     *
     * @return array
     */
    private function getDataFieldShapeSummary(array $data_fields): array
    {
        $summary = [];
        foreach ($data_fields as $index => $field) {
            $field_array = $this->normalizeDataField($field);
            if (!is_array($field_array)) {
                $summary[] = ['index' => $index, 'keys' => []];
                continue;
            }

            $summary[] = [
                'index' => $index,
                'keys' => array_values(array_unique(array_map('strval', array_keys($field_array)))),
                'schema' => $field_array['$schema'] ?? ($field_array['schema'] ?? ($field_array['schema_slug'] ?? null)),
                'version' => $field_array['version'] ?? null,
            ];
        }

        return $summary;
    }

    /**
     * Detect MDP record conflict error from update response.
     *
     * @param array $result
     *
     * @return bool
     */
    private function isRecordConflictResponse(array $result): bool
    {
        $error_text = strtolower((string) ($result['error'] ?? ''));
        if (str_contains($error_text, 'record_conflict')
            || str_contains($error_text, 'record version conflict')
            || str_contains($error_text, '409 conflict')) {
            return true;
        }

        $error_details = $result['data']['errors_detail'] ?? [];
        if (!is_array($error_details)) {
            return false;
        }

        foreach ($error_details as $detail) {
            $detail_text = strtolower((string) $detail);
            if (str_contains($detail_text, 'record_conflict')
                || str_contains($detail_text, 'record version conflict')
                || str_contains($detail_text, '409 conflict')) {
                return true;
            }
        }

        return false;
    }
}
