const { __ } = window.wp?.i18n ?? { __: (text) => text };

/**
 * Renders the "App Created" view.
 * @param {Object} state - The current dashboard state.
 * @returns {HTMLElement} - The rendered view.
 */
export function appCreatedView(state) {
    const container = document.createElement('div');
    container.className = 'wp2-app-created';

    const message = document.createElement('p');
    message.textContent = state.message || __('The application has been successfully created.');
    container.appendChild(message);

    const checkInstallationButton = document.createElement('button');
    checkInstallationButton.id = 'wp2-check-installation';
    checkInstallationButton.textContent = __('Check Installation');
    container.appendChild(checkInstallationButton);

    const startOverButton = document.createElement('button');
    startOverButton.id = 'wp2-start-over';
    startOverButton.textContent = __('Start Over');
    container.appendChild(startOverButton);

    return container;
}