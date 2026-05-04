<?php

namespace WicketAcc;

// No direct access
defined('ABSPATH') || exit;

/**
 * Thin wrapper around the centralized WicketWP\Log.
 *
 * All logging logic lives in the base plugin (wicket-wp-base-plugin).
 * This class preserves the existing WACC()->Log() API so account-centre
 * callsites require no changes.
 *
 * @see WicketWP\Log
 */
class Log
{
    // Re-export level constants for any code that references WicketAcc\Log::LOG_LEVEL_*.
    public const LOG_LEVEL_DEBUG = \WicketWP\Log::LOG_LEVEL_DEBUG;
    public const LOG_LEVEL_INFO = \WicketWP\Log::LOG_LEVEL_INFO;
    public const LOG_LEVEL_WARNING = \WicketWP\Log::LOG_LEVEL_WARNING;
    public const LOG_LEVEL_ERROR = \WicketWP\Log::LOG_LEVEL_ERROR;
    public const LOG_LEVEL_CRITICAL = \WicketWP\Log::LOG_LEVEL_CRITICAL;

    /**
     * Delegates fatal error handler registration to the base plugin's Log.
     * Kept for backward compatibility — base plugin now registers this itself.
     */
    public static function registerFatalErrorHandler(): void
    {
        // Base plugin registers the handler in wicket.php before plugins_loaded.
        // Nothing to do here; method retained so existing call in class-wicket-acc-main.php
        // does not break.
    }

    public function log(string $level, string $message, array $context = []): bool
    {
        return \Wicket()->log()->log($level, $message, $context);
    }

    public function critical(string $message, array $context = []): void
    {
        \Wicket()->log()->critical($message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        \Wicket()->log()->error($message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        \Wicket()->log()->warning($message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        \Wicket()->log()->info($message, $context);
    }

    public function debug(string $message, array $context = []): void
    {
        \Wicket()->log()->debug($message, $context);
    }
}
