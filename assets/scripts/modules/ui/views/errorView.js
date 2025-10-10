import { escapeHTML } from '../../utils.js';

const { __ } = window.wp?.i18n ?? { __: (text) => text };

export const errorView = (state) => `
    <section class="wp2-dashboard-card wp2-card-centered wp2-card-error">
        <div class="wp2-illustration wp2-illustration-error"></div>
        <h2>${__('Connection Error', 'wp2-update')}</h2>
        <p>${escapeHTML(state.message || __('GitHub returned an error while verifying your credentials.', 'wp2-update'))}</p>
        <div class="wp2-button-group">
            <button type="button" id="wp2-retry-connection" class="wp2-button wp2-button-primary">${__('Retry Connection', 'wp2-update')}</button>
            <button type="button" id="wp2-disconnect-reset" class="wp2-button wp2-button-secondary">${__('Disconnect and Start Over', 'wp2-update')}</button>
        </div>
    </section>
`;
