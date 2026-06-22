<?php

/**
 * Template Helper for Org Management.
 */

namespace WicketORM\Helpers;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Template Helper class extending the base Helper.
 */
class TemplateHelper extends Helper
{
    /**
     * Initialize WordPress hooks and filters.
     */
    public static function init(): void
    {
        add_action('template_redirect', [self::class, 'normalize_org_uuid_param'], 0);
        add_filter('query_vars', [self::class, 'add_hypermedia_query_vars']);
        add_action('parse_request', [self::class, 'maybe_handle_hypermedia_request']);
    }

    /**
     * Returns the URL for the Org Management template endpoint.
     *
     * This function returns the home URL with query parameters for hypermedia.
     *
     * @return string The URL for the Org Management template endpoint.
     */
    public static function template_url(): string
    {
        return home_url('?action=hypermedia&template=');
    }

    /**
     * Detect if it's a template request for our org management system.
     *
     * @return bool
     */
    public static function is_orgman_template_request(): bool
    {
        return isset($_REQUEST['action']) && $_REQUEST['action'] === 'hypermedia' && isset($_REQUEST['template']);
    }

    /**
     * Normalize query: redirect org_id => org_uuid across all org-management routes.
     */
    public static function normalize_org_uuid_param(): void
    {
        if (is_admin() || (defined('DOING_AJAX') && DOING_AJAX)) {
            return;
        }

        // Only act if org_id present and org_uuid missing
        $org_uuid = isset($_GET['org_uuid']) ? self::sanitize_text($_GET['org_uuid']) : '';
        $org_id = isset($_GET['org_id']) ? self::sanitize_text($_GET['org_id']) : '';

        if (empty($org_uuid) && !empty($org_id)) {
            // Build redirect to current URL with org_uuid replacing org_id
            $current_url = home_url(add_query_arg([]));
            $params = $_GET;
            $params['org_uuid'] = $org_id;
            // Build URL then explicitly remove org_id
            $redirect_url = add_query_arg(array_map('sanitize_text_field', $params), $current_url);
            $redirect_url = remove_query_arg('org_id', $redirect_url);
            wp_redirect($redirect_url);
            exit;
        }
    }

    /**
     * Get a template partial from our org-management plugin directory.
     *
     * @param string $template Template name without extension (e.g., 'organization-list')
     * @param array $args Optional arguments to pass to the template
     * @return void
     */
    public static function wicket_orgman_get_template(string $template, array $args = []): void
    {
        // Security: Allow subdirectories but only alphanumeric, hyphen, underscore segments.
        $raw_template = trim($template);
        $template_parts = array_filter(explode('/', $raw_template), static function ($part) {
            return '' !== trim($part);
        });

        if (empty($template_parts)) {
            self::log_error('Empty template name provided');
            wp_die('Invalid template name.');
            exit;
        }

        $sanitized_parts = [];
        foreach ($template_parts as $part) {
            $part = trim($part);
            if (!preg_match('/^[a-zA-Z0-9_-]+$/', $part)) {
                self::log_error('Invalid template segment: ' . $part);
                wp_die('Invalid template name.');
                exit;
            }
            $sanitized_parts[] = $part;
        }

        $relative_path = implode('/', $sanitized_parts) . '.php';

        // Build the template path within our plugin directory
        $template_dir = dirname(__DIR__); // org-roster root (src/WicketORM)

        $template_map = [
            'member-details' => $template_dir . '/templates-partials/member-details.php',
            'group-members-list' => $template_dir . '/templates-partials/group-members-list-endpoint.php',
            'process/add-group-member' => $template_dir . '/templates-partials/process/add-group-member.php',
            'process/remove-group-member' => $template_dir . '/templates-partials/process/remove-group-member.php',
            'process/update-group' => $template_dir . '/templates-partials/process/update-group.php',
        ];

        $template_path = $template_map[$template] ?? ($template_dir . '/templates-partials/' . $relative_path);

        // Security: Ensure the template file exists within our plugin directory
        $real_template_path = realpath($template_path);
        $real_plugin_dir = realpath($template_dir);

        if (!$real_template_path || !$real_plugin_dir
             || strpos($real_template_path, $real_plugin_dir) !== 0) {
            \Wicket()->log()->error('Template security check failed or file missing', [
                'source' => 'wicket-orgman',
                'path' => $template_path,
                'real_path' => $real_template_path ?: 'false',
                'plugin_dir' => $real_plugin_dir,
            ]);
            wp_die('Template not found.');
            exit;
        }

        if (file_exists($real_template_path)) {
            // Load services that templates might need
            if (!isset($args['organizations']) && $template === 'organization-list') {
                // Initialize the organization service
                $org_service = new \WicketORM\Services\OrganizationService();
                $user_uuid = wp_get_current_user()->user_login;
                $organizations = $org_service->getUserOrganizations($user_uuid);

                // Check for error responses
                if (isset($organizations['error'])) {
                    self::log_warning('Organization service returned error: ' . ($organizations['error'] ?? 'unknown'));
                    $args['error'] = $organizations;
                    $args['organizations'] = []; // Empty organizations array
                } else {
                    $args['organizations'] = $organizations;
                }
            }

            // Load Datastar PHP SDK for templates that need it
            self::load_datastar_sdk($template_dir);

            // Extract arguments to make them available in the template
            if (!empty($args)) {
                extract($args);
            }

            // Include the template file
            include $real_template_path;
        } else {
            self::log_error('Template file does not exist: ' . $real_template_path);
            wp_die('Template not found.');
            exit;
        }
    }

    /**
     * Load Datastar PHP SDK files.
     *
     * @param string $template_dir Path to template directory
     */
    private static function load_datastar_sdk(string $template_dir): void
    {
        if (class_exists(\starfederation\datastar\ServerSentEventGenerator::class)) {
            return;
        }

        self::log_error('Datastar SDK not autoloaded; ensure Composer autoload is loaded before WicketORM templates render.');
    }

    /**
     * Add custom query variables for hypermedia requests.
     *
     * @param array $query_vars The existing query variables
     * @return array The modified query variables
     */
    public static function add_hypermedia_query_vars(array $query_vars): array
    {
        $query_vars[] = 'action';
        $query_vars[] = 'template';

        return $query_vars;
    }

    /**
     * Check if this is a hypermedia request and handle it.
     *
     * @param WP $wp The WordPress environment instance
     * @return WP
     */
    public static function maybe_handle_hypermedia_request($wp)
    {
        $action = $wp->query_vars['action'] ?? $_REQUEST['action'] ?? '';
        $template = $wp->query_vars['template'] ?? $_REQUEST['template'] ?? '';

        if ($action === 'hypermedia' && !empty($template)) {
            // Normalize org_id=>org_uuid for hypermedia endpoint too
            $org_uuid = isset($_GET['org_uuid']) ? self::sanitize_text($_GET['org_uuid']) : '';
            $org_id = isset($_GET['org_id']) ? self::sanitize_text($_GET['org_id']) : '';

            if (empty($org_uuid) && !empty($org_id)) {
                $base = home_url(add_query_arg([]));
                $params = $_GET;
                $params['org_uuid'] = $org_id;
                $redirect_url = add_query_arg(array_map('sanitize_text_field', $params), $base);
                $redirect_url = remove_query_arg('org_id', $redirect_url);
                wp_redirect($redirect_url);
                exit;
            }

            // Clean output buffer
            if (ob_get_level()) {
                ob_end_clean();
            }

            // No-cache is safe for both text/html and text/event-stream responses.
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Cache-Control: post-check=0, pre-check=0', false);
            header('Pragma: no-cache');

            // Content-Type is intentionally NOT set here. SSE templates (e.g. member-details)
            // call ServerSentEventGenerator::sendHeaders() themselves to set text/event-stream;
            // plain HTML templates fall back to PHP's default text/html. Setting text/html up
            // front breaks Datastar SSE parsing for streaming templates.
            self::wicket_orgman_get_template($template, []);

            // Exit to prevent any further WordPress processing
            exit;
        }

        return $wp;
    }
}

// Legacy functions for backward compatibility
if (!function_exists('WicketORM\\Helpers\\template_url')) {
    function template_url(): string
    {
        return TemplateHelper::template_url();
    }
}

if (!function_exists('WicketORM\\Helpers\\is_orgman_template_request')) {
    function is_orgman_template_request(): bool
    {
        return TemplateHelper::is_orgman_template_request();
    }
}

if (!function_exists('WicketORM\\Helpers\\orgman_normalize_org_uuid_param')) {
    function orgman_normalize_org_uuid_param(): void
    {
        TemplateHelper::normalize_org_uuid_param();
    }
}

if (!function_exists('WicketORM\\Helpers\\wicket_orgman_get_template')) {
    function wicket_orgman_get_template(string $template, array $args = []): void
    {
        TemplateHelper::wicket_orgman_get_template($template, $args);
    }
}

if (!function_exists('WicketORM\\Helpers\\add_hypermedia_query_vars')) {
    function add_hypermedia_query_vars(array $query_vars): array
    {
        return TemplateHelper::add_hypermedia_query_vars($query_vars);
    }
}

if (!function_exists('WicketORM\\Helpers\\maybe_handle_hypermedia_request')) {
    function maybe_handle_hypermedia_request($wp)
    {
        return TemplateHelper::maybe_handle_hypermedia_request($wp);
    }
}
