import { StandardModal } from './StandardModal.js';
import { api_request } from '../../api.js';
import { modalManager } from '../../utils/modal.js';
import { AppService } from '../../services/AppService.js';

export const ManualCredentialsModal = () => {
    const bodyContent = `
        <form id="manual-credentials-form" class="wp2-form needs-validation" novalidate>
            <p>Enter the credentials for your GitHub App.</p>
            <div>
                <label for="app-id" class="form-label">App ID:</label>
                <input type="text" name="app_id" id="app-id" class="wp2-input" required />
                <div class="invalid-feedback">
                    Please provide an App ID.
                </div>
            </div>
            <div>
                <label for="installation-id" class="form-label">Installation ID:</label>
                <input type="text" name="installation_id" id="installation-id" class="wp2-input" required />
                <div class="invalid-feedback">
                    Please provide an Installation ID.
                </div>
            </div>
            <div>
                <label for="private-key" class="form-label">Private Key:</label>
                <textarea name="private_key" id="private-key" class="wp2-input" rows="5" required></textarea>
                <div class="invalid-feedback">
                    Please provide a Private Key.
                </div>
            </div>
        </form>
    `;

    const footerActions = [
        { label: 'Cancel', class: 'wp2-btn--secondary', attributes: 'data-dismiss="modal"' },
        { label: 'Save', class: 'wp2-btn--primary', attributes: 'id="save-credentials-btn"' }
    ];

    const modal = StandardModal({
        title: 'Enter Manual Credentials',
        bodyContent,
        footerActions
    });

    modal.addEventListener('click', (event) => {
        if (event.target.id === 'save-credentials-btn') {
            const form = document.getElementById('manual-credentials-form');

            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            } else {
                const appId = document.getElementById('app-id').value;
                const installationId = document.getElementById('installation-id').value;
                const privateKey = document.getElementById('private-key').value;

                // Call the API to save credentials
                api_request('credentials/manual-setup', {
                    method: 'POST',
                    body: JSON.stringify({
                        app_id: appId,
                        installation_id: installationId,
                        private_key: privateKey
                    })
                }, 'wp2_manual_setup')
                .then(() => {
                    // Fetch updated apps and close the modal
                    return AppService.fetchApps();
                })
                .then(() => {
                    modal.dispatchEvent(new Event('close'));
                    alert('Credentials saved successfully!');
                })
                .catch((error) => {
                    console.error('Error saving credentials:', error);
                    alert('Failed to save credentials. Please try again.');
                });
            }

            form.classList.add('was-validated');
        }
    });

    return modal;
};
