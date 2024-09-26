<?php

namespace WicketAcc;

// No direct access
defined('ABSPATH') || exit;

/**
 * User class for Wicket Account Centre
 *
 * For interaction between MDP and WP users.
 */
class User extends WicketAcc
{
    private $user_id = 0; // WP user ID

    /**
     * Constructor.
     */
    public function __construct()
    {
    }

    /**
     * Create a WordPress User
     *
     * @param string $username MDP UUID
     * @param string $password password
     * @param string $email Optional, but highly recommended
     *
     * @return int|false User ID or false on error
     */
    public function create_user($username, $password, $email = '')
    {
        // Do we receive data?
        if (empty($username) || empty($password)) {
            return false;
        }

        // User already exist?
        $this->user_id = $this->user_exists($username);

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
     * Return user ID if exists, false if not
     *
     * @param string $username_or_email MDP UUID or email
     *
     * @return int|false User ID or false
     */
    public function user_exists($username_or_email)
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
}
