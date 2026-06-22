<?php

/**
 * Shared Datastar SSE response utilities for Org Management modals.
 */

namespace WicketORM\Helpers;

use starfederation\datastar\enums\ElementPatchMode;
use starfederation\datastar\ServerSentEventGenerator;

if (!defined('ABSPATH') && !defined('WICKET_DOING_TESTS')) {
    exit;
}

/**
 * Static helper class for Datastar SSE operations.
 */
class DatastarSSE
{
    /**
     * Render a success message using Datastar SSE.
     *
     * @param string $message        The success message to display.
     * @param string $targetSelector The CSS selector for the target element.
     * @param array  $signalsToSet   Associative array of signal names and values to set (optional).
     * @param int    $reloadSeconds  Optional countdown in seconds before reloading page (0 disables reload).
     * @param string $countdownId    The HTML element ID for the countdown timer (used when reload is enabled).
     * @return void
     */
    public static function renderSuccess(
        string $message,
        string $targetSelector,
        array $signalsToSet = [],
        int $reloadSeconds = 0,
        string $countdownId = 'countdown',
        array $elementPatches = []
    ): void {
        $html = sprintf(
            '<div class="wt_bg-green-100 wt_border wt_border-green-400 wt_text-green-700 wt_px-4 wt_py-3 wt_rounded-sm wt_mb-4"><p><strong>%1$s</strong></p><p>%2$s</p>%3$s</div>',
            esc_html__('Success!', 'wicket-acc'),
            wp_kses_post($message),
            $reloadSeconds > 0
                ? sprintf(
                    '<p class="wt_mt-2 wt_text-sm">%1$s <span id="%2$s">%3$d</span> %4$s</p>',
                    esc_html__('This page will reload in', 'wicket-acc'),
                    esc_attr($countdownId),
                    (int) $reloadSeconds,
                    esc_html__('seconds...', 'wicket-acc')
                )
                : ''
        );

        $generator = new ServerSentEventGenerator();
        $generator->sendHeaders();

        // Set specified signals
        if (!empty($signalsToSet)) {
            $generator->patchSignals($signalsToSet);
        }

        // Show the success message
        $generator->patchElements($html, [
            'selector' => $targetSelector,
            'mode' => ElementPatchMode::Inner,
        ]);

        foreach ($elementPatches as $patch) {
            $elements = isset($patch['elements']) ? (string) $patch['elements'] : '';
            $selector = isset($patch['selector']) ? (string) $patch['selector'] : '';
            $mode = $patch['mode'] ?? ElementPatchMode::Outer;

            if ($elements === '' || $selector === '') {
                continue;
            }

            $generator->patchElements($elements, [
                'selector' => $selector,
                'mode' => $mode,
            ]);
        }

        if ($reloadSeconds > 0) {
            $countdown_script = '
                let countdown = ' . (int) $reloadSeconds . ";
                const countdownEl = document.getElementById('" . esc_js($countdownId) . "');
                const timer = setInterval(() => {
                    countdown--;
                    if (countdownEl) {
                        countdownEl.textContent = countdown;
                    }
                    if (countdown <= 0) {
                        clearInterval(timer);
                        window.location.reload();
                    }
                }, 1000);
            ";

            $generator->executeScript($countdown_script);
        }
    }

    /**
     * Render an error message using Datastar SSE.
     *
     * @param string $message        The error message to display.
     * @param string $targetSelector The CSS selector for the target element.
     * @param array  $signalsToSet   Associative array of signal names and values to set (optional).
     * @return void
     */
    public static function renderError(string $message, string $targetSelector, array $signalsToSet = []): void
    {
        $html = sprintf(
            '<div class="wt_bg-red-100 wt_border wt_border-red-400 wt_text-red-700 wt_px-4 wt_py-3 wt_rounded-sm wt_mb-4">%1$s</div>',
            esc_html($message)
        );

        $generator = new ServerSentEventGenerator();
        $generator->sendHeaders();

        // Set specified signals
        if (!empty($signalsToSet)) {
            $generator->patchSignals($signalsToSet);
        }

        // Show the error message
        $generator->patchElements($html, [
            'selector' => $targetSelector,
            'mode' => ElementPatchMode::Inner,
        ]);
    }

    /**
     * Set multiple Datastar signals at once.
     *
     * @param array $signals Associative array of signal names and values.
     * @return void
     */
    public static function setSignals(array $signals): void
    {
        $generator = new ServerSentEventGenerator();
        $generator->sendHeaders();
        $generator->patchSignals($signals);
    }

    /**
     * Execute JavaScript via Datastar SSE.
     *
     * @param string $script The JavaScript code to execute.
     * @return void
     */
    public static function executeScript(string $script): void
    {
        $generator = new ServerSentEventGenerator();
        $generator->sendHeaders();
        $generator->executeScript($script);
    }
}
