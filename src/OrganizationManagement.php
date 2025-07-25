<?php

declare(strict_types=1);

namespace WicketAcc;

// No direct access
defined('ABSPATH') || exit;

/**
 * Handles Organization Management custom templates.
 */
class OrganizationManagement extends WicketAcc
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        add_filter('wicket/acc/orgman/is_orgmanagement_page', [$this, 'getTemplate'], 10, 2);
    }

    /**
     * Filter wicket/acc/orgman/is_orgmanagement_page to add new custom templates:
     *
     * - subsidiaries.php
     * - subsidiaries-add-new.php
     *
     * @param string|bool $isAccTemplate Template slug or false
     * @param int $postId Post ID
     *
     * @return string|bool
     */
    public function getTemplate($isAccTemplate, $postId)
    {
        // Is postId custom post type my-account?
        if (get_post_type($postId) !== 'my-account') {
            return $isAccTemplate;
        }

        // Our page slug is organization-* ?
        $pageSlug = sanitize_text_field(get_post_field('post_name', $postId));

        // Starts with 'organization-'
        if (str_starts_with($pageSlug, 'organization-')) {
            // Use the rest of the slug as template name
            $templateName = str_replace('organization-', '', $pageSlug);

            // If it's "management", use "index"
            if ($templateName === 'management') {
                $templateName = 'index';
            }

            return $templateName;
        }

        return $isAccTemplate;
    }

    /**
     * Get an Org Management page URL, by slug.
     *
     * @param string $slug The page slug.
     * @param bool $noParams Skips adding query params to the URL.
     *
     * @return string The URL.
     */
    public function getPageUrl(string $slug = '', bool $noParams = false): string
    {
        // TODO: Refactor to remove dependency on this global variable.
        global $orgman_pages_slugs;

        if (empty($slug)) {
            return home_url();
        }

        // Valid slug?
        if (empty($orgman_pages_slugs) || !in_array($slug, $orgman_pages_slugs)) {
            return home_url();
        }

        if (did_action('acf/init')) {
            // Get from Carbon Fields with fallback to ACF
            if (function_exists('carbon_get_theme_option')) {
                $pageId = carbon_get_theme_option('acc_page_orgman-' . sanitize_text_field($slug));
            } else {
                $pageId = get_field('acc_page_orgman-' . sanitize_text_field($slug), 'option');
            }
        } else {
            $pageId = get_option('acc_page_orgman-' . sanitize_text_field($slug));
        }

        // Not found? Try to found the page by slug: organization-{slug}
        if (!$pageId) {
            $page = get_page_by_path('organization-' . sanitize_text_field($slug), OBJECT, 'my-account');
            $pageId = $page ? $page->ID : false;
        }

        // Not found again?
        if (!$pageId) {
            return home_url();
        }

        $pageUrl = get_permalink($pageId);

        // Check if we have URL params in the current URL, and add them to the URL
        if (strpos($_SERVER['REQUEST_URI'], '?') !== false && !$noParams) {
            // Catch all URL params
            $queryParams = parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY);
            $urlParams = [];
            parse_str($queryParams, $urlParams);

            if (!empty($urlParams)) {
                $pageUrl = add_query_arg($urlParams, $pageUrl);
            }
        }

        return $pageUrl;
    }
}
