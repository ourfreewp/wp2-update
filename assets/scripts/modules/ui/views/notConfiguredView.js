const { __ } = window.wp?.i18n ?? { __: (text) => text };

/**
 * Renders the "Not Configured" view.
 * @param {Object} state - The current dashboard state.
 * @returns {HTMLElement} - The rendered view.
 */
export function notConfiguredView(state) {
    const container = document.createElement('div');
    container.className = 'wp2-not-configured';

    const message = document.createElement('p');
    message.textContent = state.message || __('The application is not configured.');
    container.appendChild(message);

    const startButton = document.createElement('button');
    startButton.id = 'wp2-start-connection';
    startButton.textContent = __('Start Configuration');
    container.appendChild(startButton);

    const manualSetupButton = document.createElement('button');
    manualSetupButton.id = 'wp2-manual-setup';
    manualSetupButton.textContent = __('Manual Setup');
    container.appendChild(manualSetupButton);

    return container;
}