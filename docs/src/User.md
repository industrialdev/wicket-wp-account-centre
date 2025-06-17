# ACC User Class Documentation

## Overview
The `User` class provides basic utilities for interacting with WordPress users, specifically in the context of an integration where MDP (Master Data Platform) UUIDs are used as WordPress usernames. It allows for creating new WordPress users and checking for the existence of users by their MDP UUID or email.

## Class Definition
```php
namespace WicketAcc;

class User extends WicketAcc
{
    private $user_id = 0; // Stores the WP user ID after creation or check

    /**
     * Constructor.
     * Currently empty.
     */
    public function __construct();

    /**
     * Create a WordPress User.
     *
     * @param string $username MDP UUID (used as WordPress username).
     * @param string $password User's password.
     * @param string $email Optional user's email.
     *
     * @return int|false WordPress User ID if successful, false on error or if user already exists (returns existing ID in that case).
     */
    public function createUser($username, $password, $email = '');

    /**
     * Check if a user exists by username (MDP UUID) or email.
     *
     * @param string $username_or_email The MDP UUID (WordPress username) or email to check.
     *
     * @return int|false WordPress User ID if found, false otherwise.
     */
    public function userExists($username_or_email);
}
```

## Core Methods

### `__construct()`
The constructor is currently empty and does not set up any specific hooks or actions.

### `createUser($username, $password, $email = '')`
This method attempts to create a new WordPress user.
- **Parameters**:
    - `$username`: Expected to be the MDP UUID, which will be used as the WordPress `user_login`.
    - `$password`: The desired password for the new user.
    - `$email` (optional): The email address for the new user.
- **Behavior**:
    1.  Checks if `$username` or `$password` are empty; returns `false` if so.
    2.  Calls `userExists($username)` to see if a user with that MDP UUID (username) already exists. If so, it returns the existing user's ID.
    3.  If the user does not exist, it calls `wp_create_user($username, $password, $email)` to create the new user.
    4.  Stores the result (new user ID or `WP_Error` object) in the private `$user_id` property.
    5.  Returns the new user ID if creation was successful, or `false` if `wp_create_user` returned an error.
- **Return Value**: `int` (User ID) or `false`.

### `userExists($username_or_email)`
This method checks if a WordPress user exists based on a given username (expected to be an MDP UUID) or an email address.
- **Parameters**:
    - `$username_or_email`: The string to check. It will be treated as an email if it's a valid email format; otherwise, it's treated as a username (`user_login`).
- **Behavior**:
    1.  Uses `filter_var($username_or_email, FILTER_VALIDATE_EMAIL)` to determine if the input is an email.
    2.  If it's an email, calls `get_user_by('email', $username_or_email)`.
    3.  If it's not an email, calls `get_user_by('login', $username_or_email)`.
    4.  If a user object is found, it returns the `ID` of the user.
- **Return Value**: `int` (User ID) or `false`.

## Properties
- `private $user_id = 0;`: This property is used internally by the `createUser` method. It's first set with the result of `userExists($username)`. If a new user is subsequently created, `$user_id` is then updated with the new user's ID (or a `WP_Error` object). The `userExists` method itself does not modify this property.

## Usage Example
```php
// Assuming WACC() returns an instance of the main plugin class, which makes User available.
$user_handler = WACC()->user(); // Or however the User class is instantiated/accessed.

// Check if a user with MDP UUID 'some-mdp-uuid' exists
$existing_user_id = $user_handler->userExists('some-mdp-uuid');
if ($existing_user_id) {
    echo "User with MDP UUID 'some-mdp-uuid' already exists. ID: " . $existing_user_id;
} else {
    echo "User with MDP UUID 'some-mdp-uuid' does not exist.";
    
    // Create a new user
    $new_user_id = $user_handler->createUser('some-mdp-uuid', 'securePassword123', 'user@example.com');
    if ($new_user_id && !is_wp_error($new_user_id)) {
        echo "Created new user. ID: " . $new_user_id;
    } else {
        echo "Failed to create user.";
    }
}

// Check if a user with email 'user@example.com' exists
$user_by_email_id = $user_handler->userExists('user@example.com');
if ($user_by_email_id) {
    echo "User with email 'user@example.com' exists. ID: " . $user_by_email_id;
}
```

## Notes
- The class assumes that the WordPress `user_login` (username) field will store the MDP UUID when users are created or checked via this class.
- The class does not currently implement any data synchronization, metadata management beyond basic user creation, role management, or caching features.
