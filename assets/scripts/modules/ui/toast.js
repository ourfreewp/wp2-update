import Toastify from 'toastify-js';

let toastInstance;

/**
 * Enhanced toast function to include optional detailed error messages.
 * @param {string} text - The main message to display.
 * @param {'success'|'error'} [type='success'] - The type of toast (success or error).
 * @param {string} [details] - Optional detailed message for errors.
 */
export const toast = (text, type = 'success', details) => {
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
        // This is a simplified version; in a real app, you might lazy-load Toastify
        toastInstance = toast;
    }
    return toastInstance;
};