import { __ } from '@wordpress/i18n';
import { store, STATUS } from '../state/store.js';
import { escapeHtml } from '../utils/string.js';
import { PackagesView } from './PackagesView.js';
import { AppsView } from './AppsView.js';

const WelcomeView = () => `
    <div class="wp2-welcome-view">
        <h1>${__("Welcome to WP2 Update", "wp2-update")}</h1>
        <p>${__("Get started by connecting a GitHub App to manage your plugins and themes.", "wp2-update")}</p>
        <button type="button" class="wp2-btn wp2-btn--primary" data-action="open-wizard">${__("Get Started", "wp2-update")}</button>
    </div>
`;

export class DashboardView {
    /**
     * Renders the main dashboard view.
     * @param {HTMLElement} rootElement - The element to render the view into.
     */
    render(rootElement) {
        const state = store.get();

        if (state.status < STATUS.INSTALLED) {
            rootElement.innerHTML = WelcomeView();
            return;
        }

        rootElement.innerHTML = `
            <section class="wp2-dashboard">
                <header class="wp2-dashboard__header">
                    <div>
                        <h1 class="wp2-dashboard__title">${__("Dashboard", "wp2-update")}</h1>
                        <p class="wp2-dashboard__subtitle">${__("Manage your GitHub-hosted plugins and themes.", "wp2-update")}</p>
                    </div>
                    <div class="wp2-dashboard__actions">
                        <button type="button" class="wp2-btn wp2-btn--primary-outline" data-action="open-wizard">${__("Add GitHub App", "wp2-update")}</button>
                        <button type="button" id="wp2-sync-all" class="wp2-btn wp2-btn--primary" ${state.isProcessing ? "disabled" : ""}>
                            ${state.isProcessing ? __("Syncing\u2026", "wp2-update") : __("Sync All", "wp2-update")}
                        </button>
                    </div>
                </header>
                <div class="wp2-dashboard-grid">
                    <div class="wp2-dashboard-card" id="wp2-packages-panel"></div>
                    <div class="wp2-dashboard-card" id="wp2-apps-panel"></div>
                </div>
            </section>
        `;

        new PackagesView().render(document.getElementById('wp2-packages-panel'));
        new AppsView().render(document.getElementById('wp2-apps-panel'));

        // Render modal container if it doesn't exist
        if (!document.getElementById('wp2-modal-container')) {
             const modalContainer = document.createElement('div');
             modalContainer.id = 'wp2-modal-container';
             document.body.appendChild(modalContainer);
        }

        // Listen to store changes to re-render the modal
        store.subscribe(s => this.renderModal(s.modal));
    }

    /**
     * Renders the modal based on the current state.
     * @param {object} modalState - The modal state from the store.
     */
    renderModal(modalState) {
        const container = document.getElementById('wp2-modal-container');
        if (!container) return;

        if (modalState.isOpen) {
            container.innerHTML = `
                <div class="wp2-modal-overlay">
                    <div class="wp2-modal">
                        <button class="wp2-modal-close" data-action="close-modal">&times;</button>
                        ${modalState.content}
                    </div>
                </div>
            `;
            container.querySelector('[data-action="close-modal"]').addEventListener('click', () => modalManager.close());
        } else {
            container.innerHTML = '';
        }
    }
}
