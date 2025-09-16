<?php

declare(strict_types=1);

namespace WicketAcc\Admin;

// No direct access
defined('ABSPATH') || exit;

/**
 * Class for general safeguarding.
 */
class Safeguards extends \WicketAcc\WicketAcc
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        add_action('admin_init', [$this, 'deleteUnwantedFolders']);

        // Hook to run page initialization when Carbon Fields theme options are saved
        add_action('carbon_fields_theme_options_container_saved', [$this, 'maybeInitAllPages'], 10, 2);
    }

    /**
     * Check if a folder exists at the given path.
     */
    public function doesFolderExists(string $folder_path): bool
    {
        return file_exists($folder_path);
    }

    /**
     * Delete unwanted folders (.ci, .github, .git) if they exist.
     */
    public function deleteUnwantedFolders(): void
    {
        // Only on non production servers
        if (defined('WP_ENVIRONMENT_TYPE') && WP_ENVIRONMENT_TYPE === 'production') {
            return;
        }

        $folders_to_delete = [
            WICKET_ACC_PATH . '.ci/',
            WICKET_ACC_PATH . '.github/',
            WICKET_ACC_PATH . '.git/',
        ];
        foreach ($folders_to_delete as $folder) {
            if ($this->doesFolderExists($folder)) {
                $this->deleteFolderRecursive($folder);
            }
        }
    }

    /**
     * Recursively delete a folder using WP_Filesystem
     * Private method to ensure it's only used within this class.
     *
     * @param string $path Path to the folder to delete
     * @return bool True on success, false on failure
     */
    private function deleteFolderRecursive(string $path): bool
    {
        // Validate the path is within our plugin directory
        if (strpos($path, WICKET_ACC_PATH) !== 0) {
            return false;
        }

        global $wp_filesystem;

        // Initialize the WordPress filesystem API
        if (empty($wp_filesystem)) {
            require_once ABSPATH . '/wp-admin/includes/file.php';
            if (!\WP_Filesystem()) {
                return false;
            }
        }

        // Verify the path exists and is a directory
        if (!$wp_filesystem->exists($path) || !$wp_filesystem->is_dir($path)) {
            return false;
        }

        // Remove directory and all its contents
        return $wp_filesystem->rmdir($path, true);
    }

    /**
     * (RE) Create all ACC pages and ensure WooCommerce my account setup
     * Runs when Carbon Fields theme options are saved.
     *
     * @return void
     */
    public function maybeInitAllPages()
    {
        global $wpdb;

        // Step 1: Check existing ACC pages using direct database query (fastest method)
        $existingSlugs = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT post_name FROM {$wpdb->posts} WHERE post_type = %s AND post_status IN ('publish', 'draft')",
                $this->acc_post_type
            )
        );

        $existingSlugs = array_flip($existingSlugs); // Convert to associative array for faster lookups

        // Step 2: Find missing pages
        $missingPages = [];
        foreach ($this->acc_pages_map_auto_create as $slug) {
            if (!isset($existingSlugs[$slug])) {
                $missingPages[$slug] = $this->acc_pages_map[$slug] ?? ucwords(str_replace(['-', '_'], ' ', $slug));
            }
        }

        // Step 3: Create missing pages
        if (!empty($missingPages)) {
            foreach ($missingPages as $slug => $name) {
                $this->createPage($slug, $name);
            }
        }

        // Step 4: Handle WooCommerce my account page setup (4-step routine)
        $this->handleWooCommerceMyAccountPage();

        // Step 5: Flush permalinks
        flush_rewrite_rules();
    }

    /**
     * Handle WooCommerce My Account page setup.
     * Steps:
     * 1) Read WooCommerce my account page ID option.
     * 2) Read current slug and content for that page.
     * 3) Ensure the page contains the classic shortcode or the block.
     * 4) If slug differs from "my-account", resolve conflicts and rename.
     */
    public function handleWooCommerceMyAccountPage(): void
    {
        // Only proceed if WooCommerce is active
        if (!\WACC()->isWooCommerceActive()) {
            return;
        }

        // 1) Read WooCommerce my account page ID option
        $pageId = (int) get_option('woocommerce_myaccount_page_id', 0);
        if ($pageId <= 0) {
            return;
        }

        // 2) Read current slug and content for that page
        $currentSlug = get_post_field('post_name', $pageId);
        $postType = get_post_type($pageId);
        $content = get_post_field('post_content', $pageId) ?: '';

        if (!$currentSlug || $postType !== 'page') {
            return;
        }

        // 3) Ensure the page contains Woo my-account shortcode OR Customer Account block
        $hasShortcode = (function_exists('has_shortcode') && has_shortcode($content, 'woocommerce_my_account'))
            || str_contains($content, '[woocommerce_my_account');
        $hasCustomerAccountBlock = str_contains($content, 'wp:woocommerce/customer-account');
        if (!$hasShortcode && !$hasCustomerAccountBlock) {
            return;
        }

        // 4) If slug differs from "my-account", resolve conflicts and rename
        $desiredSlug = 'my-account';
        $desiredTitle = 'My Account Woo';

        $update = ['ID' => $pageId];

        // Resolve slug conflict and set slug if needed
        if ($currentSlug !== $desiredSlug) {
            // Soft delete conflicting page using desired slug
            $conflictPage = get_page_by_path($desiredSlug, OBJECT, 'page');
            if ($conflictPage && (int) $conflictPage->ID !== $pageId) {
                wp_update_post([
                    'ID'         => (int) $conflictPage->ID,
                    'post_status'=> 'trash',
                ]);
            }
            $update['post_name'] = $desiredSlug;
            $update['post_title'] = $desiredTitle;
        }

        wp_update_post($update);
    }

    /**
     * Create page for ACC
     * Index, Edit Profile, Events, Event Single, etc.
     *
     * @param string $slug
     * @param string $name
     *
     * @return mixed    ID of created page or false
     */
    public function createPage($slug, $name)
    {
        $page_id = $this->getPageIdBySlug($slug);

        if ($page_id) {
            update_field('acc_page_' . $slug, $page_id, 'option');

            return $page_id;
        }

        // Create requested page as a child of ACC index page
        $page_id = wp_insert_post(
            [
                'post_type'      => $this->acc_post_type,
                'post_author'    => wp_get_current_user()->ID,
                'post_title'     => $name,
                'post_name'      => $slug,
                'post_status'    => 'publish',
                'comment_status' => 'closed',
                'post_content'   => '',
            ]
        );

        if ($page_id) {
            // Save ACF option field
            update_field('acc_page_' . $slug, $page_id, 'option');

            return $page_id;
        }

        return false;
    }
}
