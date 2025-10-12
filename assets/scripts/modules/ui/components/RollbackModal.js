import { api_request } from '../../api.js';
import { toast } from '../toast.js';

export const RollbackModal = (pkg, onClose) => {
    const modal = document.createElement('div');
    modal.className = 'wp2-modal';

    modal.innerHTML = `
        <div class="wp2-modal-content">
            <h2>Rollback Package</h2>
            <p>Select a version to rollback to:</p>
            <select id="rollback-version">
                ${pkg.releases.map(release => `
                    <option value="${release.tag}">${release.label}</option>
                `).join('')}
            </select>
            <div class="wp2-modal-actions">
                <button id="confirm-rollback" class="wp2-button">Confirm</button>
                <button id="cancel-rollback" class="wp2-button wp2-button-secondary">Cancel</button>
            </div>
        </div>
    `;

    modal.querySelector('#confirm-rollback').addEventListener('click', async () => {
        const version = modal.querySelector('#rollback-version').value;
        try {
            await api_request('packages/manage', {
                method: 'POST',
                body: {
                    action: 'rollback',
                    package: pkg.repo,
                    version,
                },
            });
            toast('Rollback successful!', 'success');
        } catch (error) {
            toast('Rollback failed. Please try again.', 'error');
        } finally {
            onClose();
        }
    });

    modal.querySelector('#cancel-rollback').addEventListener('click', onClose);

    return modal;
};