import { escapeHtml } from '../../utils/string.js';
import { apiFetch } from '@wordpress/api-fetch';
import { NotificationService } from '../../services/NotificationService.js';
import { AppService } from '../../services/AppService.js';
import { modalManager } from '../../utils/modal.js';
import { StandardModal } from './StandardModal.js';

export const AppDetailsModal = (app) => {
    const bodyContent = `
        <dl class="wp2-detail-grid">
            <dt>Account Type</dt><dd>${escapeHtml(app.account_type)}</dd>
            <dt>App ID</dt><dd>${escapeHtml(app.app_id)}</dd>
            <dt>Installation ID</dt><dd>${escapeHtml(app.installation_id)}</dd>
        </dl>
    `;

    const footerActions = [
        { label: 'Disconnect App', class: 'wp2-btn--danger', attributes: `id="disconnect-app" data-app-id="${escapeHtml(app.app_id)}"` },
        { label: 'Close', class: 'wp2-btn--secondary', attributes: 'data-dismiss="modal"' }
    ];

    return StandardModal({
        title: escapeHtml(app.name),
        bodyContent,
        footerActions,
    });
};

document.addEventListener('click', (event) => {
    if (event.target && event.target.id === 'disconnect-app') {
        const appId = event.target.dataset.appId;
        if (!appId) {
            console.error('App ID not found on disconnect button.');
            return;
        }

        apiFetch({
            path: `/wp2-update/v1/apps/${appId}`,
            method: 'DELETE',
        })
            .then(() => {
                NotificationService.showSuccess('App disconnected successfully.');
                AppService.fetchApps();
            })
            .catch((error) => {
                NotificationService.showError('Failed to disconnect app.', error.message);
            });
    }
});
