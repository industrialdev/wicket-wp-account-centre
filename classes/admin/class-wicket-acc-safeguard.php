<?php

namespace WicketAcc;

// No direct access
defined('ABSPATH') || exit;

/**
 * Admin file for Wicket Account Centre.
 */

/**
 * Admin class of module.
 */
class AdminSettings extends WicketAcc
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        add_action('admin_init', [$this, 'delete_ci_folder']);
    }

    /**
     * Check if ./ci/ folder exists at plugin path.
     */
    public function does_folder_exists()
    {
        if (file_exists(WICKET_ACC_PATH . '.ci/')) {
            return true;
        }

        return false;
    }

    /**
     * If ./ci/ folder exists at plugin path, delete it.
     */
    public function delete_ci_folder()
    {
        if ($this->does_folder_exists()) {
            $this->delete_folder_recursive(WICKET_ACC_PATH . '.ci/');
        }
    }

    /**
     * Delete folder recursively.
     */
    public function delete_folder_recursive($folderPath)
    {
        // Ensure the folder exists
        if (!is_dir($folderPath)) {
            return;
        }

        // Get all items in the folder
        $items = array_diff(scandir($folderPath), ['.', '..']);

        foreach ($items as $item) {
            $itemPath = $folderPath . DIRECTORY_SEPARATOR . $item;

            // If it's a directory, call the function recursively
            if (is_dir($itemPath)) {
                $this->delete_folder_recursive($itemPath);
            } else {
                // If it's a file, delete it
                unlink($itemPath);
            }
        }

        // After deleting the folder contents, remove the folder itself
        rmdir($folderPath);
    }
}
