const { __ } = window.wp?.i18n ?? { __: (text) => text };

/**
 * Renders the "Configuring" view.
 * @param {Object} state - The current dashboard state.
 * @returns {HTMLElement} - The rendered view.
 */
export function configuringView(state) {
    const container = document.createElement('div');
    container.className = 'wp2-configuring';

    const message = document.createElement('p');
    message.textContent = state.message || __('The application is being configured. Please wait...');
    container.appendChild(message);

    return container;
}