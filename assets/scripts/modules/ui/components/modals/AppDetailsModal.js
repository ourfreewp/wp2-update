import { app_state } from '../../state/store';

// Modal for displaying app details
export const AppDetailsModal = (app) => {
    return `
        <div class="wp2-modal-content">
            <h2>${app.name}</h2>
            <dl class="wp2-detail-grid">
                <dt>Account Type</dt>
                <dd>${app.type}</dd>
                <dt>Installation ID</dt>
                <dd>${app.installationId}</dd>
                <dt>Webhook Status</dt>
                <dd>${app.webhookStatus}</dd>
            </dl>
        </div>
    `;
};

// Subscribe to app state updates
app_state.subscribe(() => {
    const state = app_state.get();
    const selectedApp = state.apps.find(app => app.id === state.selectedAppId);

    const modalContent = document.querySelector('.wp2-modal-content');
    if (modalContent && selectedApp) {
        modalContent.innerHTML = `
            <h2>${selectedApp.name}</h2>
            <dl class="wp2-detail-grid">
                <dt>Account Type</dt>
                <dd>${selectedApp.type}</dd>
                <dt>Installation ID</dt>
                <dd>${selectedApp.installationId}</dd>
                <dt>Webhook Status</dt>
                <dd>${selectedApp.webhookStatus}</dd>
            </dl>
        `;
    }
});