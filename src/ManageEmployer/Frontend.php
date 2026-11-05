<?php

declare(strict_types=1);

namespace WicketAcc\ManageEmployer;

// No direct access
defined('ABSPATH') || exit;

/**
 * Block-front controller: block-scoped asset enqueue at render time.
 *
 * The template calls boot() while rendering, so pages without the block
 * never load the script or styles. The script builds on WordPress core
 * script libraries (wp-api-fetch, wp-url, wp-dom-ready) and talks REST
 * JSON directly; no fragment layer exists.
 *
 * @see atlas://plans/feature-acc-manage-employer-block.md
 */
final class Frontend
{
    /**
     * Enqueue the block assets once per page render.
     */
    public static function boot(): void
    {
        static $booted = false;

        if ($booted) {
            return;
        }

        $booted = true;

        $cssPath = WICKET_ACC_PATH . 'assets/css/blocks/manage-employer.css';

        if (file_exists($cssPath)) {
            wp_enqueue_style(
                'wicket-acc-manage-employer',
                WICKET_ACC_URL . 'assets/css/blocks/manage-employer.css',
                [],
                (string) filemtime($cssPath)
            );
        }

        $jsPath = WICKET_ACC_PATH . 'assets/js/wicket-acc-manage-employer.js';

        if (file_exists($jsPath)) {
            wp_enqueue_script(
                'wicket-acc-manage-employer',
                WICKET_ACC_URL . 'assets/js/wicket-acc-manage-employer.js',
                ['wp-api-fetch', 'wp-url', 'wp-dom-ready'],
                (string) filemtime($jsPath),
                true
            );
        }
    }
}
