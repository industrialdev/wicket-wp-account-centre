<?php

namespace WicketAcc;

// No direct access
defined('ABSPATH') || exit;

/**
 * Hypermedia API Configuration
 * Configures the Hypermedia API WordPress library for Datastar usage.
 */
class HypermediaApi
{
    /**
     * HypermediaApi constructor.
     *
     * Sets up the Hypermedia API configuration and hooks.
     */
    public function __construct()
    {
        // Configure the Hypermedia API with Datastar
        add_filter('hmapi/default_options', [$this, 'configure_hypermedia_api']);

        // Register custom template path for this plugin
        add_filter('hmapi/register_template_path', [$this, 'register_template_path']);
    }

    /**
     * Configure Hypermedia API options for Datastar usage.
     *
     * @param array $defaults The default options array.
     * @return array The modified options array.
     */
    public function configure_hypermedia_api(array $defaults): array
    {
        // Set Datastar as the active library
        $defaults['active_library'] = 'datastar';

        // Use local files for privacy and performance
        $defaults['load_from_cdn'] = false;

        // Enable Datastar in the backend/admin area
        $defaults['load_datastar_backend'] = false;

        return $defaults;
    }

    /**
     * Register custom template path for this plugin.
     *
     * @param array $paths The existing template paths array.
     * @return array The modified template paths array.
     */
    public function register_template_path(array $paths): array
    {
        // Register the 'wicket-acc' namespace pointing to our hypermedia directory
        $paths['wicket-acc'] = WICKET_ACC_PATH . 'hypermedia/';

        return $paths;
    }

    /**
     * Get the endpoint URL for a Hypermedia template within this plugin.
     *
     * @param string $template_name The template name (without .hm.php extension).
     * @return string The full endpoint URL.
     */
    public static function get_endpoint_url(string $template_name): string
    {
        if (function_exists('hm_get_endpoint_url')) {
            return hm_get_endpoint_url('wicket-acc:' . $template_name);
        }

        // Fallback if the function is not available
        return home_url('/wp-html/v1/wicket-acc:' . $template_name);
    }

    /**
     * Echo the endpoint URL for a Hypermedia template within this plugin.
     *
     * @param string $template_name The template name (without .hm.php extension).
     * @return void
     */
    public static function endpoint_url(string $template_name): void
    {
        if (function_exists('hm_endpoint_url')) {
            hm_endpoint_url('wicket-acc:' . $template_name);
        } else {
            echo self::get_endpoint_url($template_name);
        }
    }

    /**
     * Check if the Hypermedia API is running in library mode.
     *
     * @return bool True if running as a library, false otherwise.
     */
    public static function is_library_mode(): bool
    {
        if (function_exists('hm_is_library_mode')) {
            return hm_is_library_mode();
        }

        // Fallback check
        return defined('HMAPI_LIBRARY_MODE') && constant('HMAPI_LIBRARY_MODE');
    }

    /**
     * Send a header response using the Hypermedia API helper.
     *
     * @param string $header_name The header name.
     * @param string $header_value The header value.
     * @return void
     */
    public static function send_header_response(string $header_name, string $header_value): void
    {
        if (function_exists('hm_send_header_response')) {
            hm_send_header_response($header_name, $header_value);
        } else {
            // Fallback
            header($header_name . ': ' . $header_value);
        }
    }

    /**
     * Validate the current request using the Hypermedia API helper.
     *
     * @return bool True if request is valid, false otherwise.
     */
    public static function validate_request(): bool
    {
        if (function_exists('hm_validate_request')) {
            return hm_validate_request();
        }

        // Fallback - basic nonce check
        return wp_verify_nonce($_REQUEST['_wpnonce'] ?? '', 'wp_rest');
    }

    /**
     * Die with a message using the Hypermedia API helper.
     *
     * @param string $message The message to display.
     * @param int $status_code The HTTP status code.
     * @return void
     */
    public static function hm_die(string $message = '', int $status_code = 400): void
    {
        if (function_exists('hm_die')) {
            hm_die($message, $status_code);
        } else {
            // Fallback
            http_response_code($status_code);
            wp_die($message);
        }
    }

    /**
     * Get sanitized request parameters.
     *
     * The Hypermedia API automatically sanitizes all GET/POST parameters
     * and makes them available as $hmvals in templates.
     *
     * @return array The sanitized parameters array.
     */
    public static function get_request_params(): array
    {
        // In Hypermedia API templates, parameters are available as $hmvals
        global $hmvals;

        if (isset($hmvals) && is_array($hmvals)) {
            return $hmvals;
        }

        // Fallback - manually sanitize if $hmvals is not available
        $params = [];
        $raw_params = array_merge($_GET, $_POST);

        foreach ($raw_params as $key => $value) {
            $sanitized_key = sanitize_key($key);
            if (is_array($value)) {
                $params[$sanitized_key] = array_map('sanitize_text_field', $value);
            } else {
                $params[$sanitized_key] = sanitize_text_field($value);
            }
        }

        return $params;
    }
}
