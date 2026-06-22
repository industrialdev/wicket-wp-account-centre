// Datastar error handling and variable definitions
(function() {
    'use strict';

    // Catch and handle Datastar evaluation errors
    window.addEventListener("error", function(e) {
        if (e.message && e.message.includes("is not defined")) {
            console.warn("Datastar evaluation error caught:", e.message);
            e.preventDefault();
        }
    });

    // Ensure common variables are defined to prevent ReferenceErrors
    // These are commonly used reCAPTCHA badge position values
    if (!window.hasOwnProperty('bottomright')) {
        Object.defineProperty(window, 'bottomright', {
            value: 'bottomright',
            writable: false,
            configurable: false
        });
    }

    if (!window.hasOwnProperty('bottomleft')) {
        Object.defineProperty(window, 'bottomleft', {
            value: 'bottomleft',
            writable: false,
            configurable: false
        });
    }

    if (!window.hasOwnProperty('inline')) {
        Object.defineProperty(window, 'inline', {
            value: 'inline',
            writable: false,
            configurable: false
        });
    }
})();
