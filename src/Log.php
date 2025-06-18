<?php

namespace WicketAcc;

// No direct access
defined('ABSPATH') || exit;

/**
 * Handles plugin-specific logging with daily rotation and source-based grouping.
 * Logs are stored in wp-content/uploads/wicket-logs/.
 */
class Log
{
    public const LOG_LEVEL_DEBUG = 'debug';
    public const LOG_LEVEL_INFO = 'info';
    public const LOG_LEVEL_WARNING = 'warning';
    public const LOG_LEVEL_ERROR = 'error';
    public const LOG_LEVEL_CRITICAL = 'critical';

    private static bool $log_dir_setup_done = false;
    private static ?string $log_base_dir = null;

    /**
     * Logs a message to a custom file.
     *
     * Mimics WC_Logger::log functionality with daily rotation and source-based grouping.
     * Logs are stored in wp-content/uploads/wicket-logs/.
     *
     * @param string $level   Log level (e.g., Log::LOG_LEVEL_DEBUG, 'info', 'error').
     * @param string $message Log message.
     * @param array  $context Context for the log message. Expected: ['source' => 'my-source'].
     *                        If 'source' is not provided, 'wicket-plugin' will be used.
     * @return bool True if the message was logged successfully to the custom file, false otherwise.
     */
    public function log(string $level, string $message, array $context = []): bool
    {
        // Log CRITICAL and ERROR messages regardless of WP_DEBUG.
        // For other levels (DEBUG, INFO, WARNING), log only if WP_DEBUG is on.
        if ($level !== self::LOG_LEVEL_CRITICAL && $level !== self::LOG_LEVEL_ERROR) {
            if (!defined('WP_DEBUG') || !WP_DEBUG) {
                return true; // WP_DEBUG is off, so don't log DEBUG, INFO, or WARNING messages.
            }
        }

        if (!self::$log_dir_setup_done) {
            if (!$this->setup_log_directory()) {
                // Fallback to standard PHP error log if setup fails
                error_log("Wicket Log Directory Setup Failed. Original log: [{$level}] {$message}");

                return false;
            }
            self::$log_dir_setup_done = true;
        }

        $source = sanitize_file_name($context['source'] ?? 'wicket-plugin');
        if (empty($source)) {
            $source = 'wicket-plugin'; // Ensure source is not empty after sanitization
        }

        $date_suffix = date('Y-m-d');
        $file_hash = wp_hash($source);
        $filename = "{$source}-{$date_suffix}-{$file_hash}.log";
        $log_file_path = self::$log_base_dir . $filename;

        $timestamp = date('Y-m-d\\TH:i:s\\Z'); // ISO 8601 UTC
        $formatted_level = strtoupper($level);
        $log_entry = "{$timestamp} [{$formatted_level}]: {$message}" . PHP_EOL;

        if (!error_log($log_entry, 3, $log_file_path)) {
            // Fallback to standard PHP error log if custom file write fails
            error_log("Wicket Log File Write Failed to {$log_file_path}. Original log: [{$level}] {$message}");

            return false;
        }

        return true;
    }

    /**
     * Logs a CRITICAL message.
     *
     * @param string $message The message to log.
     * @param array  $context Optional context data.
     * @return void
     */
    public function critical(string $message, array $context = []): void
    {
        $this->log(self::LOG_LEVEL_CRITICAL, $message, $context);
    }

    /**
     * Logs an ERROR message.
     *
     * @param string $message The message to log.
     * @param array  $context Optional context data.
     * @return void
     */
    public function error(string $message, array $context = []): void
    {
        $this->log(self::LOG_LEVEL_ERROR, $message, $context);
    }

    /**
     * Logs a WARNING message.
     *
     * @param string $message The message to log.
     * @param array  $context Optional context data.
     * @return void
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log(self::LOG_LEVEL_WARNING, $message, $context);
    }

    /**
     * Logs an INFO message.
     *
     * @param string $message The message to log.
     * @param array  $context Optional context data.
     * @return void
     */
    public function info(string $message, array $context = []): void
    {
        $this->log(self::LOG_LEVEL_INFO, $message, $context);
    }

    /**
     * Logs a DEBUG message.
     *
     * @param string $message The message to log.
     * @param array  $context Optional context data.
     * @return void
     */
    public function debug(string $message, array $context = []): void
    {
        $this->log(self::LOG_LEVEL_DEBUG, $message, $context);
    }

    /**
     * Sets up the log directory, ensuring it exists and is secured.
     *
     * @return bool True if setup was successful or already done, false on critical failure.
     */
    private function setup_log_directory(): bool
    {
        if (self::$log_base_dir === null) {
            $upload_dir = wp_upload_dir();
            if (!empty($upload_dir['error'])) {
                error_log('Wicket Log Error: Could not get WordPress upload directory. ' . $upload_dir['error']);

                return false;
            }
            self::$log_base_dir = $upload_dir['basedir'] . '/wicket-logs/';
        }

        if (!is_dir(self::$log_base_dir)) {
            if (!wp_mkdir_p(self::$log_base_dir)) {
                error_log('Wicket Log Error: Could not create log directory: ' . self::$log_base_dir);

                return false;
            }
        }

        $htaccess_file = self::$log_base_dir . '.htaccess';
        if (!file_exists($htaccess_file)) {
            $htaccess_content = 'deny from all' . PHP_EOL . 'Require all denied' . PHP_EOL;
            if (@file_put_contents($htaccess_file, $htaccess_content) === false) {
                error_log('Wicket Log Error: Could not create .htaccess file in ' . self::$log_base_dir);
            }
        }

        $index_html_file = self::$log_base_dir . 'index.html';
        if (!file_exists($index_html_file)) {
            $index_content = '<!-- Silence is golden. -->';
            if (@file_put_contents($index_html_file, $index_content) === false) {
                error_log('Wicket Log Error: Could not create index.html file in ' . self::$log_base_dir);
            }
        }

        return true;
    }
}
