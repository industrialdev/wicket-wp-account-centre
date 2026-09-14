(function () {
  'use strict';

  var POLL_INTERVAL_MS = 4000;

  /**
   * confirmation_renewal callout: opens a <dialog> modal styled by the shared
   * assets/css/_wicket-acc-modal.css partial (see render_confirmation_renewal_callout())
   * instead of window.confirm(), then POSTs to the memberships plugin's
   * confirm_renewal endpoint from the modal's own confirm button. Once queued
   * (202), polls renewal_order_status (indeterminate — no per-item progress
   * exists, wcs_create_renewal_order() is one atomic call) and swaps the
   * spinner for a payment link once the order exists, or an error if the job
   * failed. Mirrors assets/js/wicket-acc-profile-image-mdp-fields.js's
   * fetch/disable/branch/re-enable shape.
   */
  function init() {
    var wrappers = document.querySelectorAll('[data-wicket-acc-confirmation-renewal]');

    Array.prototype.forEach.call(wrappers, function (wrapper) {
      var modalId = wrapper.getAttribute('data-modal-id');
      var modal = modalId ? document.getElementById(modalId) : null;
      var confirmButton = modal ? modal.querySelector('[data-wicket-acc-confirmation-renewal-confirm]') : null;
      var status = wrapper.querySelector('[data-wicket-acc-confirmation-renewal-status]');
      var links = wrapper.querySelector('[data-wicket-acc-confirmation-renewal-links]');
      var preparing = wrapper.querySelector('[data-wicket-acc-confirmation-renewal-preparing]');
      if (!status || !links || !preparing || wrapper.dataset.pollBound) {
        return;
      }

      wrapper.dataset.pollBound = '1';

      function showStatus(message, isError) {
        status.textContent = message;
        status.hidden = false;
        status.style.color = isError ? '#d63638' : '#008a20';
      }

      function pollRenewalOrderStatus() {
        var statusUrl = wrapper.getAttribute('data-status-url');
        var nonce = wrapper.getAttribute('data-nonce');
        var errorLabel = wrapper.getAttribute('data-error-label') || 'Could not confirm renewal. Please try again.';
        var viewInvoiceLabel = wrapper.getAttribute('data-view-invoice-label') || 'View your invoice';

        fetch(statusUrl, {
          method: 'GET',
          credentials: 'same-origin',
          headers: { 'X-WP-Nonce': nonce }
        })
          .then(function (response) { return response.json(); })
          .then(function (data) {
            if (!data || data.status === 'pending') {
              window.setTimeout(pollRenewalOrderStatus, POLL_INTERVAL_MS);
              return;
            }

            preparing.hidden = true;

            if (data.status === 'complete' && data.payment_url) {
              var link = document.createElement('a');
              link.href = data.payment_url;
              link.textContent = viewInvoiceLabel;
              // Matches wicket-wp-base-plugin's get_component('button', ['variant' =>
              // 'primary', 'a_tag' => true, ...]) markup so this renders identically
              // to a real button instead of a plain text link.
              link.className = 'component-button inline-flex items-center button button--primary';
              links.innerHTML = '';
              links.hidden = false;
              links.appendChild(link);
              return;
            }

            showStatus(errorLabel, true);
          })
          .catch(function () {
            window.setTimeout(pollRenewalOrderStatus, POLL_INTERVAL_MS);
          });
      }

      // Reflect whatever this cycle's claim state already is on load, instead of
      // always starting from the confirm button — a reload after confirming
      // must not let the member re-open the confirm modal, and a reload after
      // completion must not lose the invoice link.
      if (wrapper.getAttribute('data-initial-state') === 'pending') {
        window.setTimeout(pollRenewalOrderStatus, POLL_INTERVAL_MS);
      }

      if (modal && confirmButton && !confirmButton.dataset.bound) {
        confirmButton.dataset.bound = '1';

        confirmButton.addEventListener('click', function () {
          var url = wrapper.getAttribute('data-confirm-url');
          // Forward the debug day-offset param so a simulated renewal window that
          // shows this button also allows confirming it (dev/staging only).
          var debugDays = new URLSearchParams(window.location.search).get('wicket_wp_membership_debug_days');
          if (debugDays) {
            url += (url.indexOf('?') === -1 ? '?' : '&') + 'wicket_wp_membership_debug_days=' + encodeURIComponent(debugDays);
          }
          var nonce = wrapper.getAttribute('data-nonce');
          var confirmingLabel = wrapper.getAttribute('data-confirming-label') || 'Confirming…';
          var alreadyRenewedLabel = wrapper.getAttribute('data-already-renewed-label') || 'This membership bundle has already been renewed for the current cycle.';
          var errorLabel = wrapper.getAttribute('data-error-label') || 'Could not confirm renewal. Please try again.';
          var originalText = confirmButton.textContent;
          var button = confirmButton;

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
              // 409 (already queued/created this cycle) is a real, expected
              // outcome — parse its body rather than treating it as a failure.
              if (response.status === 409) {
                response.json().then(function (data) {
                  showStatus((data && data.error) || alreadyRenewedLabel, true);
                }).catch(function () {
                  showStatus(alreadyRenewedLabel, true);
                });
                modal.close();
                return null;
              }
              // 202: creation is queued, not finished. Swap the trigger button for
              // an indeterminate "preparing" spinner and start polling for the
              // order — there is no per-item progress to show, just eventual
              // completion or failure.
              if (response.status === 202) {
                links.innerHTML = '';
                links.hidden = true;
                preparing.hidden = false;
                modal.close();
                window.setTimeout(pollRenewalOrderStatus, POLL_INTERVAL_MS);
                return null;
              }
              return response.json().then(function (data) {
                throw new Error((data && data.error) || 'HTTP ' + response.status);
              });
            })
            .then(function (data) {
              if (data === null) {
                // Already handled by the 409/202 branches above.
                return;
              }
            })
            .catch(function (err) {
              showStatus((err && err.message) || errorLabel, true);
            })
            .finally(function () {
              if (button.isConnected) {
                button.disabled = false;
                button.textContent = originalText;
              }
            });
        });
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
