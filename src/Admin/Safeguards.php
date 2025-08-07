
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
        add_action('admin_init', [$this, 'delete_unwanted_folders']);
    }

    /**
     * Check if a folder exists at the given path.
     */
    public function does_folder_exists(string $folder_path): bool
    {
        return file_exists($folder_path);
    }

    /**
     * Delete unwanted folders (.ci, .github, .git) if they exist.
     */
    public function delete_unwanted_folders(): void
    {
        $folders_to_delete = [
            WICKET_ACC_PATH . '.ci/',
            WICKET_ACC_PATH . '.github/',
            WICKET_ACC_PATH . '.git/',
        ];
        foreach ($folders_to_delete as $folder) {
            if ($this->does_folder_exists($folder)) {
                $this->delete_folder_recursive($folder);
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
    private function delete_folder_recursive(string $path): bool
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
}
