import { detectedPackagesSection } from './components/detectedPackagesSection.js';

const { __ } = window.wp?.i18n ?? { __: (text) => text };

export const notConfiguredView = (state) => {
    const hasPackages = state.status === 'not_configured_with_packages' && state.unlinkedPackages.length;
    return `
        <div class="wp2-dashboard-grid" role="region" aria-labelledby="not-configured-heading">
            <h1 id="not-configured-heading" class="screen-reader-text">${__('Not Configured', 'wp2-update')}</h1>
            <section class="wp2-dashboard-card wp2-card-centered">
                <div class="wp2-illustration wp2-illustration-connect" aria-hidden="true"></div>
                <h2>${__('Connect to GitHub', 'wp2-update')}</h2>
                <p>${__('Enable automatic updates for your themes and plugins by connecting a GitHub App.', 'wp2-update')}</p>
                <div class="wp2-button-group">
                    <button type="button" id="wp2-start-connection" class="wp2-button wp2-button-primary">${__('Connect to GitHub', 'wp2-update')}</button>
                    <button type="button" id="wp2-manual-setup" class="wp2-button wp2-button-secondary">${__('Manual Setup', 'wp2-update')}</button>
                </div>
            </section>
            ${hasPackages ? detectedPackagesSection(state.unlinkedPackages) : ''}
        </div>
    `;
};
