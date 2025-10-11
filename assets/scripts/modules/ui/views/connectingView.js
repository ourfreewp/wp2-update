const { __ } = window.wp?.i18n ?? { __: (text) => text };

/**
 * Renders the "Connecting" view.
 * @returns {HTMLElement} - The rendered view.
 */
export function connectingView() {
    const container = document.createElement('div');
    container.className = 'wp2-connecting';

    const message = document.createElement('p');
    message.textContent = __('Connecting to the application. Please wait...');
    container.appendChild(message);

    return container;
}