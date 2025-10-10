import { escapeHTML } from '../../utils.js';

const { __ } = window.wp?.i18n ?? { __: (text) => text };

export const appCreatedView = (state) => `
    <section class="wp2-dashboard-card wp2-card-centered" role="region" aria-labelledby="app-created-heading">
        <h1 id="app-created-heading" class="screen-reader-text">${__('App Created', 'wp2-update')}</h1>
        <div class="wp2-illustration wp2-illustration-install"></div>
        <h2>${__('Almost there! Install the App', 'wp2-update')}</h2>
        <p>${escapeHTML(state.message || __('Finish installing the GitHub App in the tab that opened. Once complete, click the button below.', 'wp2-update'))}</p>
        <div class="wp2-button-group">
            <button type="button" id="wp2-check-installation" class="wp2-button wp2-button-primary">${__('Check Installation Status', 'wp2-update')}</button>
            <button type="button" id="wp2-start-over" class="wp2-button wp2-button-secondary">${__('Start Over', 'wp2-update')}</button>
        </div>
        ${state.polling?.active ? `<p class="wp2-muted">${__('Waiting for installation… Checking again shortly.', 'wp2-update')}</p>` : ''}
    </section>
`;
