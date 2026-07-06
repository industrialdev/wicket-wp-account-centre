(function () {
  'use strict';

  function initOrganizationProfileDemographics() {
    const app = document.getElementById('org-management-index-app');
    if (!app) {
      return;
    }

    const orgType = (app.dataset.orgType || '').trim();
    if (!orgType) {
      return;
    }

    const isProfessionalAssociation = orgType === 'professional_association';
    const selectorToRemove = isProfessionalAssociation ? '.partner_demographics' : '.assoc_demographics';

    document.querySelectorAll(selectorToRemove).forEach((node) => node.remove());
  }

  function parseMessages(app) {
    return {
      validNumber: app.dataset.msgValidNumber || 'Please enter a valid number for additional seats.',
      noNegative: app.dataset.msgNoNegative || 'Number of additional seats cannot be negative.',
      noZero: app.dataset.msgNoZero || 'You cannot purchase zero seats. Please enter a number greater than 0 to proceed with your purchase.',
      wholeNumber: app.dataset.msgWholeNumber || 'Please enter a whole number for additional seats.'
    };
  }

  function validateSeatValue(numberInput, messages) {
    const value = parseFloat(numberInput.value);

    if (isNaN(value) || numberInput.value === '') {
      alert(messages.validNumber);
      numberInput.focus();
      return false;
    }

    if (value < 0) {
      alert(messages.noNegative);
      numberInput.focus();
      numberInput.value = '';
      return false;
    }

    if (value === 0) {
      alert(messages.noZero);
      numberInput.focus();
      return false;
    }

    if (parseFloat(value) !== parseInt(value, 10)) {
      alert(messages.wholeNumber);
      numberInput.focus();
      return false;
    }

    return true;
  }

  function initSupplementalMembersValidation() {
    const app = document.getElementById('orgman-supplemental-members-app');
    if (!app) {
      return;
    }

    const numberInputId = app.dataset.numberInputId || 'input_59_3';
    const submitButtonId = app.dataset.submitButtonId || 'gform_submit_button_59';
    const numberInput = document.getElementById(numberInputId);
    const form = numberInput ? numberInput.closest('form') : null;

    if (!numberInput || !form) {
      return;
    }

    const messages = parseMessages(app);

    numberInput.setAttribute('min', '0');
    numberInput.setAttribute('step', '1');

    numberInput.addEventListener('input', function () {
      if (this.value === '' || parseFloat(this.value) < 0) {
        this.value = '';
      } else if (this.value && parseFloat(this.value) !== parseInt(this.value, 10)) {
        this.value = parseInt(this.value, 10);
      }
    });

    numberInput.addEventListener('blur', function () {
      const value = parseFloat(this.value);
      if (isNaN(value) || value < 0) {
        this.value = '';
      } else {
        this.value = Math.max(0, parseInt(value, 10));
      }
    });

    if (
      typeof window.gform !== 'undefined' &&
      window.gform.submission &&
      typeof window.gform.submission.handleButtonClick === 'function' &&
      !window.gform.submission.__orgmanSeatValidationPatched
    ) {
      const originalHandleButtonClick = window.gform.submission.handleButtonClick;
      window.gform.submission.handleButtonClick = function (button) {
        if (button && button.id === submitButtonId) {
          if (!validateSeatValue(numberInput, messages)) {
            return false;
          }
        }

        return originalHandleButtonClick.call(this, button);
      };
      window.gform.submission.__orgmanSeatValidationPatched = true;
    }

    const submitButton = form.querySelector('#' + submitButtonId);
    if (submitButton) {
      submitButton.addEventListener('click', function (event) {
        if (!validateSeatValue(numberInput, messages)) {
          event.preventDefault();
          event.stopPropagation();
        }
      });
    }
  }

  function init() {
    initOrganizationProfileDemographics();
    initSupplementalMembersValidation();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init, { once: true });
  } else {
    init();
  }
})();
