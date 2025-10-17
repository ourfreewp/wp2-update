import { LitElement, html, css } from 'lit';

class UiSpinner extends LitElement {


    createRenderRoot() {
        return this; // Disable shadow DOM
    }
    static styles = css`
        .spinner {
            border: 4px solid rgba(0, 0, 0, 0.1);
            border-left-color: #000;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }
    `;

    render() {
        return html`<div class="spinner"></div>`;
    }


    createRenderRoot() {
        return this; // Disable shadow DOM
    }
}

customElements.define('ui-spinner', UiSpinner);

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
