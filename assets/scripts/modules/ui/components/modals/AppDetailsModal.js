import { unified_state } from '../../state/store';

// Modal for displaying app details
export const AppDetailsModal = (app) => {
    return `
        <div class="wp2-modal-content">
            <div class="wp2-modal-header">
                <h2>${app.name}</h2>
            </div>
            <div class="wp2-modal-body">
                <dl class="wp2-detail-grid">
                    <dt>Account Type</dt>
                    <dd>${app.type}</dd>
                    <dt>Installation ID</dt>
                    <dd>${app.installationId}</dd>
                    <dt>Webhook Status</dt>
                    <dd>${app.webhookStatus}</dd>
                </dl>
            </div>
            <div class="wp2-modal-footer">
                <button class="wp2-btn wp2-btn-secondary">Close</button>
            </div>
        </div>
    `;
};

// Subscribe to unified state updates
unified_state.subscribe(() => {
    const state = unified_state.get();
    const selectedApp = state.apps.find(app => app.id === state.selectedAppId);

    const modalContent = document.querySelector('.wp2-modal-content');
    if (modalContent && selectedApp) {
        modalContent.innerHTML = `
            <div class="wp2-modal-header">
                <h2>${selectedApp.name}</h2>
            </div>
            <div class="wp2-modal-body">
                <dl class="wp2-detail-grid">
                    <dt>Account Type</dt>
                    <dd>${selectedApp.type}</dd>
                    <dt>Installation ID</dt>
                    <dd>${selectedApp.installationId}</dd>
                    <dt>Webhook Status</dt>
                    <dd>${selectedApp.webhookStatus}</dd>
                </dl>
            </div>
            <div class="wp2-modal-footer">
                <button class="wp2-btn wp2-btn-secondary">Close</button>
            </div>
        `;
    }
});