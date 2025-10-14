import { logger } from '../utils/logger.js';

export const NotificationService = {
    /**
     * Ensures the toast element exists in the DOM.
     * @returns {HTMLElement} The toast element.
     */
    ensureToastElement() {
        let toastEl = document.getElementById('wp2-toast');
        if (!toastEl) {
            toastEl = document.createElement('div');
            toastEl.id = 'wp2-toast';
            toastEl.className = 'toast position-fixed bottom-0 end-0 p-3';
            toastEl.setAttribute('role', 'alert');
            toastEl.setAttribute('aria-live', 'assertive');
            toastEl.setAttribute('aria-atomic', 'true');
            toastEl.innerHTML = `
                <div class="toast-body"></div>
            `;
            document.body.appendChild(toastEl);
        }
        return toastEl;
    },

    /**
     * Shows a success toast notification.
     * @param {string} message - The message to display.
     */
    showSuccess(message) {
        logger.info(`Notification (Success): ${message}`);
        const toastEl = this.ensureToastElement();
        toastEl.querySelector('.toast-body').textContent = message;
        toastEl.classList.remove('bg-danger', 'bg-warning');
        toastEl.classList.add('bg-success');
        const toast = new bootstrap.Toast(toastEl);
        toast.show();
    },

    /**
     * Shows an error toast notification.
     * @param {string} message - The main error message.
     * @param {string} [details] - Optional detailed error information.
     */
    showError(message, details = '') {
        logger.error(`Notification (Error): ${message}`, details);
        const fullMessage = details ? `${message}\nDetails: ${details}` : message;
        const toastEl = this.ensureToastElement();
        toastEl.querySelector('.toast-body').textContent = fullMessage;
        toastEl.classList.remove('bg-success', 'bg-warning');
        toastEl.classList.add('bg-danger');
        const toast = new bootstrap.Toast(toastEl);
        toast.show();
    },

    /**
     * Shows a warning toast notification.
     * @param {string} message - The warning message to display.
     * @param {string} [details] - Optional detailed warning information.
     */
    showWarning(message, details = '') {
        logger.warn(`Notification (Warning): ${message}`, details);
        const fullMessage = details ? `${message}\nDetails: ${details}` : message;
        const toastEl = this.ensureToastElement();
        toastEl.querySelector('.toast-body').textContent = fullMessage;
        toastEl.classList.remove('bg-success', 'bg-danger');
        toastEl.classList.add('bg-warning');
        const toast = new bootstrap.Toast(toastEl);
        toast.show();
    },
};
