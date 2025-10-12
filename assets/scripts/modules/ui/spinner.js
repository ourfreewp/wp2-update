import { Logger } from '../utils.js';

// Utility functions for managing a global loading spinner

/**
 * Logs spinner visibility changes for debugging purposes.
 * @param {string} action - The action performed (e.g., 'show' or 'hide').
 */
const logSpinnerAction = (action) => {
    Logger.info(`Global Spinner: ${action}`);
};

/**
 * Show the global loading spinner.
 */
export const show_global_spinner = () => {
    const id = 'wp2-global-spinner';
    if (document.getElementById(id)) return;
    const el = document.createElement('div');
    el.id = id;
    el.className = 'wp2-global-spinner';
    document.body.appendChild(el);
    logSpinnerAction('show'); // Log spinner visibility
};

/**
 * Hide the global loading spinner.
 */
export const hide_global_spinner = () => {
    document.getElementById('wp2-global-spinner')?.remove();
    logSpinnerAction('hide'); // Log spinner visibility
};

/**
 * Show a localized loading spinner for a specific element.
 * @param {HTMLElement} element - The element to attach the spinner to.
 */
export const show_local_spinner = (element) => {
    if (!element) return;
    const spinner = document.createElement('div');
    spinner.className = 'wp2-local-spinner';
    element.appendChild(spinner);
    Logger.info('Local Spinner: show');
};

/**
 * Hide a localized loading spinner for a specific element.
 * @param {HTMLElement} element - The element to remove the spinner from.
 */
export const hide_local_spinner = (element) => {
    if (!element) return;
    const spinner = element.querySelector('.wp2-local-spinner');
    if (spinner) {
        spinner.remove();
        Logger.info('Local Spinner: hide');
    }
};
