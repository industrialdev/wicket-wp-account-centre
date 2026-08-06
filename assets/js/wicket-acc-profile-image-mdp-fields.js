(function () {
  'use strict';

  /**
   * Rebuild the profile-image MDP field <select> from a fresh grouped list.
   *
   * @param {HTMLSelectElement} select
   * @param {Array} groups       Grouped field list from the REST response.
   * @param {string}  keepValue  Previously selected composite ref to preserve.
   */
  function rebuildSelect(select, groups, keepValue) {
    var noSyncingLabel = select.getAttribute('data-wicket-no-syncing-label') || 'No syncing';
    keepValue = keepValue || '';

    select.innerHTML = '';

    var empty = document.createElement('option');
    empty.value = '';
    empty.textContent = noSyncingLabel;
    if (keepValue === '') {
      empty.selected = true;
    }
    select.appendChild(empty);

    groups.forEach(function (group) {
      var schemaSlug = group.schema_slug || '';
      var schemaLabel = group.schema_label || schemaSlug;
      var fields = group.fields || [];
      if (!fields.length) {
        return;
      }

      var optgroup = document.createElement('optgroup');
      optgroup.label = schemaLabel;

      fields.forEach(function (field) {
        var option = document.createElement('option');
        var ref = schemaSlug + '|' + (field.slug || '');
        option.value = ref;
        option.textContent = field.label || field.slug;
        if (ref === keepValue) {
          option.selected = true;
        }
        optgroup.appendChild(option);
      });

      select.appendChild(optgroup);
    });
  }

  function init() {
    var wrappers = document.querySelectorAll('[data-wicket-acc-mdp-fields]');

    Array.prototype.forEach.call(wrappers, function (wrapper) {
      var button = wrapper.querySelector('[data-wicket-acc-mdp-fields-refresh-button]');
      var select = wrapper.querySelector('[data-wicket-acc-mdp-fields-select]');
      var spinner = wrapper.querySelector('[data-wicket-acc-mdp-fields-spinner]');
      var status = wrapper.querySelector('[data-wicket-acc-mdp-fields-status]');
      if (!button || !select || !status || button.dataset.bound) {
        return;
      }

      button.dataset.bound = '1';

      var fadeTimer = null;
      var hideTimer = null;

      function clearStatusTimers() {
        if (fadeTimer) { clearTimeout(fadeTimer); fadeTimer = null; }
        if (hideTimer) { clearTimeout(hideTimer); hideTimer = null; }
      }

      function hideStatus() {
        clearStatusTimers();
        status.style.display = 'none';
        status.style.opacity = '1';
        status.style.transition = '';
      }

      // Show a status line, then fade it out after 10 seconds.
      function showStatus(message, isError) {
        clearStatusTimers();
        status.textContent = message;
        status.style.transition = '';
        status.style.opacity = '1';
        status.style.display = 'inline-block';
        status.style.color = isError ? '#d63638' : '#008a20';

        fadeTimer = setTimeout(function () {
          status.style.transition = 'opacity 1s ease';
          status.style.opacity = '0';
        }, 10000);

        hideTimer = setTimeout(function () {
          status.style.display = 'none';
          status.style.transition = '';
        }, 11000);
      }

      button.addEventListener('click', function () {
        var url = button.getAttribute('data-rest-url');
        var nonce = button.getAttribute('data-nonce');
        var originalText = button.textContent;
        var successLabel = button.getAttribute('data-success-label') || 'Field list refreshed.';
        var errorLabel = button.getAttribute('data-error-label') || 'Refresh failed.';

        button.disabled = true;
        button.textContent = button.getAttribute('data-refreshing-label') || 'Refreshing...';
        if (spinner) {
          spinner.style.display = 'inline-block';
          spinner.classList.add('is-active');
        }
        hideStatus();

        fetch(url, {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'X-WP-Nonce': nonce,
            'Content-Type': 'application/json'
          }
        })
          .then(function (response) {
            if (!response.ok) {
              throw new Error('HTTP ' + response.status);
            }
            return response.json();
          })
          .then(function (data) {
            var groups = data && Array.isArray(data.fields) ? data.fields : [];
            rebuildSelect(select, groups, select.value);
            showStatus(successLabel, false);
          })
          .catch(function () {
            showStatus(errorLabel, true);
          })
          .finally(function () {
            button.disabled = false;
            button.textContent = originalText;
            if (spinner) {
              spinner.classList.remove('is-active');
              spinner.style.display = 'none';
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
