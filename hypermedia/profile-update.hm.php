<?php
/**
 * User Profile Update Template.
 *
 * This template handles user profile updates using Datastar.
 * Access this template via: /wp-html/v1/wicket-acc:profile-update
 */

// Security check
if (!is_user_logged_in()) {
    http_response_code(401);
    echo '<div class="error">You must be logged in to update your profile.</div>';

    return;
}

$current_user = wp_get_current_user();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $current_user->ID;
    $display_name = sanitize_text_field($_POST['display_name'] ?? '');
    $description = sanitize_textarea_field($_POST['description'] ?? '');

    $updated = false;
    $errors = [];

    // Validate and update display name
    if (!empty($display_name)) {
        $result = wp_update_user([
            'ID' => $user_id,
            'display_name' => $display_name,
        ]);

        if (!is_wp_error($result)) {
            $updated = true;
        } else {
            $errors[] = 'Failed to update display name: ' . $result->get_error_message();
        }
    }

    // Update user meta
    if (!empty($description)) {
        update_user_meta($user_id, 'description', $description);
        $updated = true;
    }

    // Return response
    if ($updated && empty($errors)) {
        echo '<div class="success-message bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">';
        echo 'Profile updated successfully!';
        echo '</div>';
    } elseif (!empty($errors)) {
        echo '<div class="error-message bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">';
        foreach ($errors as $error) {
            echo '<p>' . esc_html($error) . '</p>';
        }
        echo '</div>';
    }

    return;
}

// Display the form
?>
<form data-on-submit="@post('<?php echo WicketAcc\HypermediaApi::get_endpoint_url('profile-update'); ?>')"
      data-target="#update-result"
      class="space-y-4">

    <div>
        <label for="display_name" class="block text-sm font-medium text-gray-700">
            Display Name
        </label>
        <input type="text"
               id="display_name"
               name="display_name"
               value="<?php echo esc_attr($current_user->display_name); ?>"
               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
               required>
    </div>

    <div>
        <label for="description" class="block text-sm font-medium text-gray-700">
            Bio/Description
        </label>
        <textarea id="description"
                  name="description"
                  rows="4"
                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"><?php echo esc_textarea(get_user_meta($current_user->ID, 'description', true)); ?></textarea>
    </div>

    <div>
        <button type="submit"
                class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
            Update Profile
        </button>
    </div>
</form>

<div id="update-result" class="mt-4"></div>
