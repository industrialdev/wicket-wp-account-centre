(function () {
  'use strict';

  if (window.NotificationSystem && window.notificationSystem) {
    return;
  }

  class NotificationSystem {
    constructor() {
      this.container = document.getElementById('notification-container');
      this.notifications = [];
      this.notificationId = 0;
    }

    show(options = {}) {
      const {
        type = 'info',
        title = '',
        message = '',
        autoClose = true,
        duration = 5000,
        inline = false
      } = options;

      const notification = this.createNotification(type, title, message, autoClose, duration, inline);

      if (inline) {
        this.showInline(notification);
      } else {
        this.showFloating(notification);
      }

      return notification;
    }

    createNotification(type, title, message, autoClose, duration, inline) {
      const notificationId = ++this.notificationId;
      const notification = document.createElement('div');
      notification.className = 'notification notification-' + type + (inline ? ' notification-inline' : '');
      notification.dataset.notificationId = notificationId;

      const icons = {
        success: '\u2713',
        error: '\u2715',
        warning: '!',
        info: 'i'
      };

      notification.innerHTML =
        '<div class="notification-icon">' + (icons[type] || icons.info) + '</div>' +
        '<div class="notification-content">' +
          (title ? '<div class="notification-title">' + this.escapeHtml(title) + '</div>' : '') +
          '<div class="notification-message">' + this.escapeHtml(message) + '</div>' +
        '</div>' +
        '<button class="notification-close" aria-label="Close" type="button">&times;</button>';

      const closeButton = notification.querySelector('.notification-close');
      if (closeButton) {
        closeButton.addEventListener('click', () => this.hide(notificationId));
      }

      if (autoClose && !inline) {
        setTimeout(() => this.hide(notificationId), duration);
      }

      return {
        id: notificationId,
        element: notification,
        autoClose,
        duration,
        inline
      };
    }

    showFloating(notification) {
      if (!this.container) {
        return;
      }

      this.container.appendChild(notification.element);
      setTimeout(() => {
        notification.element.classList.add('notification-show');
      }, 10);

      this.notifications.push(notification);
    }

    showInline(notification) {
      let inlineContainer = document.querySelector('.notifications-inline-container');
      if (!inlineContainer) {
        inlineContainer = document.createElement('div');
        inlineContainer.className = 'notifications-inline-container';

        const mainContent =
          document.querySelector('.org-management section > .container') ||
          document.querySelector('.wicket-orgman') ||
          document.querySelector('main .container');

        if (mainContent) {
          mainContent.insertBefore(inlineContainer, mainContent.firstChild);
        }
      }

      if (!inlineContainer) {
        return;
      }

      inlineContainer.appendChild(notification.element);
      this.notifications.push(notification);
    }

    hide(notificationId) {
      const notification = this.notifications.find((n) => n.id === notificationId);
      if (!notification) {
        return;
      }

      notification.element.classList.add('notification-hide');

      setTimeout(() => {
        if (notification.element.parentNode) {
          notification.element.parentNode.removeChild(notification.element);
        }

        this.notifications = this.notifications.filter((n) => n.id !== notificationId);

        const inlineContainer = document.querySelector('.notifications-inline-container');
        if (inlineContainer && inlineContainer.children.length === 0) {
          inlineContainer.remove();
        }
      }, 300);
    }

    success(message, title = '', options = {}) {
      return this.show({ type: 'success', title, message, ...options });
    }

    error(message, title = '', options = {}) {
      return this.show({ type: 'error', title, message, ...options });
    }

    warning(message, title = '', options = {}) {
      return this.show({ type: 'warning', title, message, ...options });
    }

    info(message, title = '', options = {}) {
      return this.show({ type: 'info', title, message, ...options });
    }

    clear() {
      this.notifications.forEach((notification) => {
        if (notification.element.parentNode) {
          notification.element.parentNode.removeChild(notification.element);
        }
      });

      this.notifications = [];

      const inlineContainer = document.querySelector('.notifications-inline-container');
      if (inlineContainer) {
        inlineContainer.remove();
      }
    }

    escapeHtml(text) {
      const div = document.createElement('div');
      div.textContent = text;
      return div.innerHTML;
    }
  }

  window.NotificationSystem = NotificationSystem;

  const init = function () {
    const notificationSystem = new NotificationSystem();
    window.notificationSystem = notificationSystem;

    document.addEventListener('show-notification', function (e) {
      const detail = e && e.detail ? e.detail : {};
      const type = detail.type;
      const title = detail.title;
      const message = detail.message;
      const autoClose = detail.autoClose;
      const duration = detail.duration;
      const inline = detail.inline;

      notificationSystem.show({ type, title, message, autoClose, duration, inline });
    });

    const convertLegacyNotices = function () {
      const legacyNotices = document.querySelectorAll('.notice:not(.notification)');
      legacyNotices.forEach((notice) => {
        const type =
          notice.classList.contains('notice--success') ? 'success' :
          notice.classList.contains('notice--error') ? 'error' :
          'info';

        const message = notice.textContent.trim();
        const title = type.charAt(0).toUpperCase() + type.slice(1);

        notificationSystem.show({ type, title, message, inline: true });

        notice.style.display = 'none';
        setTimeout(() => notice.remove(), 500);
      });
    };

    convertLegacyNotices();

    const observer = new MutationObserver(function (mutations) {
      mutations.forEach(function (mutation) {
        if (mutation.addedNodes) {
          convertLegacyNotices();
        }
      });
    });

    observer.observe(document.body, {
      childList: true,
      subtree: true
    });
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init, { once: true });
  } else {
    init();
  }
})();
