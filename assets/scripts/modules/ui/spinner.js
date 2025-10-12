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
