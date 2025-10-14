import { logger } from '../../utils/logger.js';

const SPINNER_ID = 'wp2-global-spinner';

/**
 * Shows the global loading spinner.
 */
export const show_global_spinner = () => {
    if (document.getElementById(SPINNER_ID)) return;
    const el = document.createElement('div');
    el.id = SPINNER_ID;
    el.className = 'wp2-global-spinner';
    document.body.appendChild(el);
    logger.info('Global Spinner: show');
};

/**
 * Hides the global loading spinner.
 */
export const hide_global_spinner = () => {
    document.getElementById(SPINNER_ID)?.remove();
    logger.info('Global Spinner: hide');
};
