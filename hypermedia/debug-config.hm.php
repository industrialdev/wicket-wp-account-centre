<?php
/**
 * Debug Template - Check Datastar Configuration.
 *
 * Access this template via: /wp-html/v1/wicket-acc:debug-config
 */

// Get Hypermedia API options to check configuration
$options = get_option('hmapi_options', []);

// Apply our filter to see what our configuration sets
$defaults = apply_filters('hmapi/default_options', []);

echo '<div style="background: #f0f0f0; padding: 20px; margin: 20px; border-radius: 5px;">';
echo '<h3>Hypermedia API Configuration Debug</h3>';

echo '<h4>Current WordPress Options (hmapi_options):</h4>';
echo '<pre>' . print_r($options, true) . '</pre>';

echo '<h4>Applied Filter Defaults (hmapi/default_options):</h4>';
echo '<pre>' . print_r($defaults, true) . '</pre>';

echo '<h4>Datastar Check:</h4>';
echo '<ul>';
echo '<li>Active Library: ' . ($defaults['active_library'] ?? 'not set') . '</li>';
echo '<li>Load Datastar: ' . ($defaults['load_datastar'] ?? 'not set') . '</li>';
echo '<li>Load Datastar Backend: ' . ($defaults['load_datastar_backend'] ?? 'not set') . '</li>';
echo '<li>Load from CDN: ' . ($defaults['load_from_cdn'] ?? 'not set') . '</li>';
echo '</ul>';

echo '<h4>Function Checks:</h4>';
echo '<ul>';
echo '<li>hm_get_endpoint_url exists: ' . (function_exists('hm_get_endpoint_url') ? 'YES' : 'NO') . '</li>';
echo '<li>hm_endpoint_url exists: ' . (function_exists('hm_endpoint_url') ? 'YES' : 'NO') . '</li>';
echo '<li>ServerSentEventGenerator class exists: ' . (class_exists('HMApi\\starfederation\\datastar\\ServerSentEventGenerator') ? 'YES' : 'NO') . '</li>';
echo '</ul>';

echo '<h4>Current Request Info:</h4>';
echo '<ul>';
echo '<li>Current URL: ' . $_SERVER['REQUEST_URI'] . '</li>';
echo '<li>Method: ' . $_SERVER['REQUEST_METHOD'] . '</li>';
echo '<li>User Agent: ' . $_SERVER['HTTP_USER_AGENT'] . '</li>';
echo '</ul>';

echo '</div>';
