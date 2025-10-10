import { managedPackagesTable } from './components/managedPackagesTable.js';
import { emptyPackagesState } from './components/emptyPackagesState.js';
import { escapeHTML } from '../../utils.js';

const { __, sprintf } = window.wp?.i18n ?? { __: (text) => text, sprintf: (...parts) => parts.join(' ') };

export const dashboardView = (state) => {
    const packages = state.packages || [];
    const message = state.details.app_name
        ? sprintf(__('Connection to %s is active.', 'wp2-update'), escapeHTML(state.details.app_name))
        : __('Connection to GitHub is active.', 'wp2-update');

    return `
        <div class="wp2-dashboard-grid" role="region" aria-labelledby="dashboard-heading">
            <h1 id="dashboard-heading" class="screen-reader-text">${__('Dashboard', 'wp2-update')}</h1>
            <section class="wp2-dashboard-card">
                <header class="wp2-dashboard-header">
                    <div>
                        <h2>${__('Managed Packages', 'wp2-update')}</h2>
                        <p class="wp2-muted">${message}</p>
                    </div>
                    <div class="wp2-button-group">
                        <button type="button" id="wp2-sync-packages" class="wp2-button wp2-button-secondary">${__('Sync Packages', 'wp2-update')}</button>
                        <button type="button" id="wp2-disconnect" class="wp2-button wp2-button-danger">${__('Disconnect', 'wp2-update')}</button>
                    </div>
                </header>
                ${packages.length ? managedPackagesTable(packages) : emptyPackagesState()}
            </section>
        </div>
    `;
};
