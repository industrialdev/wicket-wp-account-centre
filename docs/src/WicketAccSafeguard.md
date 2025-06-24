# ACC WicketAccSafeguard Class Documentation

## Overview
The `WicketAccSafeguard` class is a security and cleanup utility for the Wicket Account Centre plugin. Its sole purpose is to automatically remove development-related folders that may have been included in a production build, such as `.git`, `.github`, and `.ci`. This process runs automatically on the `admin_init` hook.

## Class Definition
```php
namespace WicketAcc\Admin;

class WicketAccSafeguard extends \WicketAcc\WicketAcc
{
    /**
     * Constructor.
     */
    public function __construct();

    /**
     * Check if a folder exists at the given path.
     */
    public function does_folder_exists($folder_path);

    /**
     * Delete unwanted folders (.ci, .github, .git) if they exist.
     */
    public function delete_unwanted_folders();
}
```

## Core Functionality

The class operates automatically upon instantiation. Here’s how it works:

1.  **Initialization**: The `__construct` method hooks the `delete_unwanted_folders` method into the `admin_init` action, ensuring it runs during the WordPress admin area initialization.

2.  **Folder Detection**: The `delete_unwanted_folders` method checks for the existence of the following directories within the plugin's root path:
    - `.ci/`
    - `.github/`
    - `.git/`

3.  **Secure Deletion**: If any of these folders are found, the class uses a private, recursive deletion method that leverages the `WP_Filesystem` API. This ensures that file operations are performed safely and reliably.

## Security
- The recursive deletion function includes a crucial check to ensure it only operates within the plugin's own directory (`WICKET_ACC_PATH`), preventing it from deleting files or folders elsewhere on the server.
- By removing version control and CI/CD folders, it helps reduce the attack surface and prevents exposure of potentially sensitive repository information on a live site.

## Usage
This class is designed to work automatically in the background. It is instantiated once in the main plugin file and requires no further configuration or interaction from a developer.

There are no public methods intended for direct calls, as its functionality is entirely self-contained and triggered by a WordPress action hook.
