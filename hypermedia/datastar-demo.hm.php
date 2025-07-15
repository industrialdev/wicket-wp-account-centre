<?php

/**
 * Datastar Demo Template.
 *
 * This template demonstrates basic Datastar functionality with Server-Sent Events.
 * Access this template via: /wp-html/v1/wicket-acc:datastar-demo
 */

use HMApi\starfederation\datastar\ServerSentEventGenerator;

// Required headers for SSE
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');

// Get signals sent from the frontend
$signals = ServerSentEventGenerator::readSignals();
$delay = $signals['delay'] ?? 100; // Default delay in milliseconds
$message = $signals['message'] ?? 'Hello from Wicket Account Centre!';

$sse = new ServerSentEventGenerator();

// Stream the message character by character
for ($i = 0; $i < strlen($message); $i++) {
    $sse->patchElements(
        '<div id="datastar-output">'
        . esc_html(substr($message, 0, $i + 1))
        . '<span class="cursor">|</span></div>'
    );

    // Sleep for the provided delay in milliseconds
    usleep($delay * 1000);
}

// Final output without cursor
$sse->patchElements(
    '<div id="datastar-output">'
    . esc_html($message)
    . '</div>'
);

// The script will automatically exit and send the SSE stream
