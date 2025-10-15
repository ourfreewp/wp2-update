import { logger } from '../utils/logger.js';

export const NotificationService = {
    /**
     * Ensures the toast container exists in the DOM and returns the toast element itself.
     */
    ensureToastElement() {
        let container = document.getElementById('wp2-toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'wp2-toast-container';
            // Use Bootstrap utility classes for fixed positioning at the bottom right
            container.className = 'toast-container position-fixed bottom-0 end-0 p-3'; 
            container.setAttribute('aria-live', 'polite'); // Live region for accessibility
            document.body.appendChild(container);
        }

        // Create a unique toast element for each call to allow stacking
        const toastEl = document.createElement('div');
        // Role is set based on severity in the showX functions
        toastEl.className = 'toast align-items-center border-0'; 
        toastEl.setAttribute('role', 'status');
        toastEl.setAttribute('aria-atomic', 'true');
        toastEl.setAttribute('data-bs-delay', '7000'); // Set a reasonable default delay
        
        toastEl.innerHTML = `
            <div class="d-flex">
                <div class="toast-body"></div>
                <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        `;

        container.appendChild(toastEl);
        return toastEl;
    },

    /**
     * Shows a success toast notification.
     */
    showSuccess(message) {
        logger.info(`Notification (Success): ${message}`);
        const toastEl = this.ensureToastElement();
        
        // Use text-bg for colored backgrounds and set polite status
        toastEl.className = 'toast align-items-center border-0 text-bg-success';
        toastEl.setAttribute('role', 'status'); 
        
        toastEl.querySelector('.toast-body').textContent = message;
        
        // Remove white close button in case it was set by an error
        toastEl.querySelector('.btn-close').classList.remove('btn-close-white');

        const toast = new bootstrap.Toast(toastEl);
        toast.show();
    },

    /**
     * Shows an error toast notification.
     */
    showError(message, details = '') {
        logger.error(`Notification (Error): ${message}`, details);
        const fullMessage = details ? `${message}\nDetails: ${details}` : message;
        const toastEl = this.ensureToastElement();
        
        // Use text-bg-danger and set assertive role for errors
        toastEl.className = 'toast align-items-center border-0 text-bg-danger';
        toastEl.setAttribute('role', 'alert'); 
        toastEl.setAttribute('aria-live', 'assertive');

        toastEl.querySelector('.toast-body').textContent = fullMessage;
        
        // Ensure the close button is visible on a dark background
        toastEl.querySelector('.btn-close').classList.add('btn-close-white');

        const toast = new bootstrap.Toast(toastEl);
        toast.show();
    }
};
