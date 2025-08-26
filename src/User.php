<?php

namespace WicketAcc;

// No direct access
defined('ABSPATH') || exit;

/**
 * User class for Wicket Account Centre.
 *
 * For interaction between MDP and WP users.
 */
class User extends WicketAcc
{
    private $user_id = 0; // WP user ID

    /**
     * Constructor.
     */
    public function __construct() {}

    /**
     * Create a WordPress User.
     *
     * @param string $username MDP UUID
     * @param string $password password
     * @param string $email Optional, but highly recommended
     *
     * @return int|false User ID or false on error
     */
    public function createUser($username, $password, $email = '')
    {
        // Do we receive data?
        if (empty($username) || empty($password)) {
            return false;
        }

        // User already exist?
        $this->user_id = $this->userExists($username);

        if ($this->user_id) {
            return $this->user_id;
        }

        // Create user
        $this->user_id = wp_create_user($username, $password, $email);

        // Error?
        if (is_wp_error($this->user_id)) {
            return false;
        }

        return $this->user_id;
    }

    /**
     * Check if an user exists, by username or email
     * Username in our case, is MDP UUID
     * Return user ID if exists, false if not.
     *
     * @param string $username_or_email MDP UUID or email
     *
     * @return int|false User ID or false
     */
    public function userExists($username_or_email)
    {
        // Email or not?
        if (filter_var($username_or_email, FILTER_VALIDATE_EMAIL)) {
            $user = get_user_by('email', $username_or_email);
        } else {
            $user = get_user_by('login', $username_or_email);
        }

        // Found
        if ($user && is_object($user)) {
            return $user->ID;
        }

        return false;
    }

    /**
     * Used if a user exists in the MDP but not WP, and you need to sync them
     * down on a one-off basis, for example processing an order or for roster management.
     *
     * @param string $uuid UUID of their MDP person
     * @param string $first_name (optional) First name override, if needed
     * @param string $last_name  (optional) Last name override, if needed
     * @param string $femail     (optional) Email override, if needed
     *
     * @return bool | int        Will return false if there was a problem, and their new
     *                           WP user ID if successful.
     */
    /**
     * Creates a WordPress user from an MDP person record if they don't already exist.
     * Used for syncing a user on-demand (e.g., processing an order, roster management).
     *
     * @param string|null $uuid       UUID of the MDP person.
     * @param string|null $firstName  Optional first name override.
     * @param string|null $lastName   Optional last name override.
     * @param string|null $email      Optional email override.
     *
     * @return int|false The new WP user ID if successful, otherwise false.
     */
    public function createOrUpdateWpUser(?string $uuid, ?string $firstName = null, ?string $lastName = null, ?string $email = null): int|false
    {
        if (empty($uuid)) {
            WACC()->Log()->warning('createOrUpdateWpUser called with an empty UUID.', ['source' => __METHOD__]);

            return false;
        }

        // If overrides are not provided, fetch data from MDP
        if (is_null($firstName) || is_null($lastName) || is_null($email)) {
            $mdp_person = WACC()->Mdp()->Person()->getPersonByUuid($uuid);
            if (!$mdp_person || !isset($mdp_person->attributes)) {
                WACC()->Log()->error('Failed to retrieve person data from MDP for user creation/update.', ['source' => __METHOD__, 'uuid' => $uuid]);

                return false;
            }
            $attributes = $mdp_person->attributes;
            $firstName ??= $attributes->given_name ?? null;
            $lastName ??= $attributes->family_name ?? null;
            $email ??= $attributes->primary_email_address ?? null;
        }

        if (empty($email)) {
            WACC()->Log()->error('Email is missing for user creation/update.', ['source' => __METHOD__, 'uuid' => $uuid]);

            return false;
        }

        $user = get_user_by('login', $uuid) ?: get_user_by('email', $email);

        if ($user) {
            // Update existing user
            $user_data = [
                'ID'         => $user->ID,
                'user_email' => $email,
                'first_name' => $firstName,
                'last_name'  => $lastName,
            ];
            $user_id = wp_update_user($user_data);
            if (is_wp_error($user_id)) {
                WACC()->Log()->error(
                    'Failed to update WordPress user.',
                    ['source' => __METHOD__, 'uuid' => $uuid, 'error' => $user_id->get_error_message()]
                );

                return false;
            }

            return $user_id;
        } else {
            // Create new user
            $user_data = [
                'user_email'   => $email,
                'user_pass'    => wp_generate_password(16, false),
                'user_login'   => sanitize_user($uuid),
                'display_name' => trim($firstName . ' ' . $lastName),
                'first_name'   => $firstName,
                'last_name'    => $lastName,
                'role'         => 'subscriber',
            ];
            $user_id = wp_insert_user($user_data);
            if (is_wp_error($user_id)) {
                WACC()->Log()->error(
                    'Failed to create WordPress user.',
                    ['source' => __METHOD__, 'uuid' => $uuid, 'error' => $user_id->get_error_message()]
                );

                return false;
            }

            return $user_id;
        }
    }

    /**
     * Assign one or more WordPress roles to a user.
     *
     * @param string $personUuid The person's UUID (user_login) or email address.
     * @param string|string[] $roles The role or roles to assign.
     * @return bool True on success, false on failure.
     */
    public function assignWpRoles(string $personUuid, string|array $roles): bool
    {
        return $this->updateUserRoles($personUuid, $roles, 'add');
    }

    /**
     * Remove one or more WordPress roles from a user.
     *
     * @param string $personUuid The person's UUID (user_login) or email address.
     * @param string|string[] $roles The role or roles to remove.
     * @return bool True on success, false on failure.
     */
    public function removeWpRoles(string $personUuid, string|array $roles): bool
    {
        return $this->updateUserRoles($personUuid, $roles, 'remove');
    }

    /**
     * Private helper to add or remove roles from a user.
     *
     * @param string $personUuid The person's UUID (user_login) or email address.
     * @param string|string[] $roles The role or roles to update.
     * @param string $action The action to perform ('add' or 'remove').
     * @return bool True on success, false on failure.
     */
    private function updateUserRoles(string $personUuid, string|array $roles, string $action): bool
    {
        if (empty($personUuid) || empty($roles)) {
            WACC()->Log()->warning('User role update skipped: Missing person UUID or roles.', [
                'source' => __METHOD__,
                'person_uuid' => $personUuid,
                'action' => $action,
            ]);

            return false;
        }

        $user = get_user_by('login', $personUuid) ?? get_user_by('email', $personUuid);

        if (!$user) {
            WACC()->Log()->error('Failed to find user for role update.', [
                'source' => __METHOD__,
                'person_uuid' => $personUuid,
                'action' => $action,
            ]);

            return false;
        }

        $roles = (array) $roles;
        $actionMethod = $action === 'add' ? 'add_role' : 'remove_role';

        foreach ($roles as $role) {
            $user->$actionMethod($role);
        }

        return true;
    }
}
