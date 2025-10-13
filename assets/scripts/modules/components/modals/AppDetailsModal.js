import { escapeHtml } from '../../utils/string.js';
import { apiDelete } from '../../api.js';

export const AppDetailsModal = (app) => `
    <div class="wp2-modal-header">
        <h2>${escapeHtml(app.name)}</h2>
    </div>
    <div class="wp2-modal-body">
        <dl class="wp2-detail-grid">
            <dt>Account Type</dt><dd>${escapeHtml(app.account_type)}</dd>
            <dt>App ID</dt><dd>${escapeHtml(app.app_id)}</dd>
            <dt>Installation ID</dt><dd>${escapeHtml(app.installation_id)}</dd>
        </dl>
        <button class="wp2-button wp2-button--danger" id="disconnect-app">Disconnect App</button>
    </div>
`;

document.addEventListener('click', (event) => {
    if (event.target && event.target.id === 'disconnect-app') {
        apiDelete(`/apps/${app.app_id}`)
            .then(() => {
                NotificationService.showSuccess('App disconnected successfully.');
                AppService.fetchApps();
                modalManager.close();
            })
            .catch((error) => {
                console.error('Failed to disconnect app:', error);
                NotificationService.showError('Failed to disconnect app. Please try again.');
            });
    }
});
