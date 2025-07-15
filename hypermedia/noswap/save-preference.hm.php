<?php

/**
 * Save User Preference - No Swap Template.
 *
 * This template saves user preferences without returning HTML.
 * Access this template via: /wp-html/v1/wicket-acc:noswap/save-preference
 */

// Security check
if (!is_user_logged_in()) {
    http_response_code(401);

    return;
}

// Nonce verification (WordPress automatically checks nonces for wp-html endpoints)
$user_id = get_current_user_id();
$preference_key = sanitize_key($_POST['preference_key'] ?? $_GET['preference_key'] ?? '');
$preference_value = sanitize_text_field($_POST['preference_value'] ?? $_GET['preference_value'] ?? '');

if (empty($preference_key)) {
    http_response_code(400);

    return;
}

// Save the preference
$result = update_user_meta($user_id, 'wicket_acc_' . $preference_key, $preference_value);

if ($result !== false) {
    // Send success header
    header('HX-Trigger: preference-saved');
    http_response_code(200);
} else {
    // Send error header
    header('HX-Trigger: preference-error');
    http_response_code(500);
}
