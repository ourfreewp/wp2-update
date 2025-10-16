import { StandardModal } from './StandardModal.js';
import { apiFetch } from '../../utils/apiFetch.js';

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
        footerActions,
    });

    document.getElementById('save-credentials-btn').addEventListener('click', async () => {
        const form = document.getElementById('manual-credentials-form');
        if (!form.checkValidity()) {
            form.classList.add('was-validated');
            return;
        }

        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());

        try {
            await apiFetch({
                path: '/wp2-update/v1/apps/manual-credentials',
                method: 'POST',
                data,
            });
            alert('Credentials saved successfully.');
        } catch (error) {
            alert('Failed to save credentials.');
        }
    });

    return modal;
};
