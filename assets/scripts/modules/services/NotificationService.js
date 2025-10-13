import Toastify from 'toastify-js';
import { logger } from '../utils/logger.js';

export const NotificationService = {
    /**
     * Shows a success toast notification.
     * @param {string} message - The message to display.
     */
    showSuccess(message) {
        logger.info(`Notification (Success): ${message}`);
        Toastify({
            text: message,
            duration: 3000,
            gravity: "bottom",
            position: "right",
            style: { background: "#4CAF50" },
        }).showToast();
    },

    /**
     * Shows an error toast notification.
     * @param {string} message - The main error message.
     * @param {string} [details] - Optional detailed error information.
     */
    showError(message, details = '') {
        logger.error(`Notification (Error): ${message}`, details);
        const fullMessage = details ? `${message}\nDetails: ${details}` : message;
        Toastify({
            text: fullMessage,
            duration: 5000,
            gravity: "bottom",
            position: "right",
            style: { background: "#f44336" },
        }).showToast();
    },
};
