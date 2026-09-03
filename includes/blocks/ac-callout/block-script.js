(function () {
  'use strict';

  /**
   * confirmation_renewal callout button: a confirm dialog, then a POST to
   * the memberships plugin's confirm_renewal endpoint, with the result shown
   * in place. Mirrors assets/js/wicket-acc-profile-image-mdp-fields.js's
   * fetch/disable/branch/re-enable shape.
   */
  function init() {
    var wrappers = document.querySelectorAll('[data-wicket-acc-confirmation-renewal]');

    Array.prototype.forEach.call(wrappers, function (wrapper) {
      var button = wrapper.querySelector('[data-wicket-acc-confirmation-renewal-button]');
      var status = wrapper.querySelector('[data-wicket-acc-confirmation-renewal-status]');
      if (!button || !status || button.dataset.bound) {
        return;
      }

      button.dataset.bound = '1';

      function showStatus(message, isError) {
        status.textContent = message;
        status.hidden = false;
        status.style.color = isError ? '#d63638' : '#008a20';
      }

      button.addEventListener('click', function () {
        var confirmMessage = wrapper.getAttribute('data-confirm-message') || 'Are you sure?';
        if (!window.confirm(confirmMessage)) {
          return;
        }

        var url = wrapper.getAttribute('data-confirm-url');
        var nonce = wrapper.getAttribute('data-nonce');
        var confirmingLabel = wrapper.getAttribute('data-confirming-label') || 'Confirming…';
        var successLabel = wrapper.getAttribute('data-success-label') || 'Renewal confirmed successfully.';
        var alreadyRenewedLabel = wrapper.getAttribute('data-already-renewed-label') || 'This membership bundle has already been renewed for the current cycle.';
        var errorLabel = wrapper.getAttribute('data-error-label') || 'Could not confirm renewal. Please try again.';
        var originalText = button.textContent;

        button.disabled = true;
        button.textContent = confirmingLabel;
        status.hidden = true;

        fetch(url, {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'X-WP-Nonce': nonce,
            'Content-Type': 'application/json'
          }
        })
          .then(function (response) {
            // 409 (already renewed this cycle) is a real, expected outcome —
            // parse its body rather than treating it as a generic failure.
            if (response.status === 409) {
              showStatus(alreadyRenewedLabel, true);
              return null;
            }
            if (!response.ok) {
              throw new Error('HTTP ' + response.status);
            }
            return response.json();
          })
          .then(function (data) {
            if (data === null) {
              // Already handled by the 409 branch above.
              return;
            }
            if (!data || !data.success) {
              showStatus(errorLabel, true);
              return;
            }
            showStatus(successLabel, false);
            // A successful confirm is terminal for this cycle — remove the
            // button so a second click isn't offered. Idempotency is also
            // enforced server-side (a second POST would 409), so this is a
            // UX nicety, not the actual safeguard.
            button.remove();
          })
          .catch(function () {
            showStatus(errorLabel, true);
          })
          .finally(function () {
            if (button.isConnected) {
              button.disabled = false;
              button.textContent = originalText;
            }
          });
      });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
