const { __ } = window.wp?.i18n ?? { __: (text) => text };

/**
 * Renders the "Error" view.
 * @param {Object} state - The current dashboard state.
 * @returns {HTMLElement} - The rendered view.
 */
export function errorView(state) {
    const container = document.createElement('div');
    container.className = 'wp2-error';

    const message = document.createElement('p');
    message.textContent = state.message || __('An error occurred. Please try again.');
    container.appendChild(message);

    return container;
}