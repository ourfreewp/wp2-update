// Notification Service

import Toastify from 'toastify-js';

/**
 * Show a success notification.
 * @param {string} message - The message to display.
 */
export const showSuccessNotification = (message) => {
    Toastify({
        text: message,
        duration: 4000,
        gravity: 'bottom',
        position: 'right',
        stopOnFocus: true,
        style: {
            background: '#4CAF50',
        },
    }).showToast();
};

/**
 * Show an error notification.
 * @param {string} message - The message to display.
 * @param {string} [details] - Optional detailed message.
 */
export const showErrorNotification = (message, details) => {
    const fullMessage = details ? `${message}\nDetails: ${details}` : message;
    Toastify({
        text: fullMessage,
        duration: 4000,
        gravity: 'bottom',
        position: 'right',
        stopOnFocus: true,
        style: {
            background: '#f44336',
        },
    }).showToast();
};