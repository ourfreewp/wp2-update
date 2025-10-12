import Toastify from 'toastify-js';
import { Logger } from '../utils.js';

let toastInstance;

/**
 * Logs toast notifications for debugging purposes.
 * @param {string} text - The main message to display.
 * @param {'success'|'error'} [type='success'] - The type of toast (success or error).
 * @param {string} [details] - Optional detailed message for errors.
 */
const logToast = (text, type, details) => {
    Logger.info(`Toast Notification: [${type.toUpperCase()}] ${text}`);
    if (details) {
        Logger.debug(`Details: ${details}`);
    }
};

/**
 * Enhanced toast function to include optional detailed error messages.
 * @param {string} text - The main message to display.
 * @param {'success'|'error'} [type='success'] - The type of toast (success or error).
 * @param {string} [details] - Optional detailed message for errors.
 */
export const toast = (text, type = 'success', details) => {
    logToast(text, type, details); // Log the toast notification

    const message = details && type === 'error' ? `${text}\nDetails: ${details}` : text;
    Toastify({
        text: message,
        duration: 4000,
        gravity: 'bottom',
        position: 'right',
        stopOnFocus: true,
        style: {
            background: type === 'success' ? '#4CAF50' : '#f44336',
        },
    }).showToast();
};

export const ensureToast = async () => {
    if (!toastInstance) {
        toastInstance = toast;
    }
    return toastInstance;
};

/**
 * Displays a toast notification with server error details.
 * @param {Response} response - The fetch API response object.
 */
export const toastServerError = async (response) => {
    try {
        const errorData = await response.json();
        const errorMessage = errorData.message || `HTTP ${response.status} ${response.statusText}`;
        toast('Server Error', 'error', errorMessage);
    } catch {
        toast('Server Error', 'error', `HTTP ${response.status} ${response.statusText}`);
    }
};
