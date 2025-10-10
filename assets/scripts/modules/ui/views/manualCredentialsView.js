import { api_request } from '../../api.js';
import { show_global_spinner, hide_global_spinner } from '../../ui/spinner.js';
import { ensureToast } from '../../ui/toast.js';

const { __ } = window.wp?.i18n ?? { __: (text) => text };

export const renderManualCredentialsForm = () => `
    <section class="wp2-dashboard-card" role="region" aria-labelledby="manual-configuring-heading">
        <h2 id="manual-configuring-heading">${__('Manual GitHub App Setup', 'wp2-update')}</h2>
        <p class="wp2-muted">${__('Enter the credentials for your manually created GitHub App.', 'wp2-update')}</p>
        <form id="wp2-manual-configure-form" class="wp2-form">
            <label class="wp2-label">${__('Encryption Key', 'wp2-update')}
                <input type="password" name="encryption_key" class="wp2-input" placeholder="${__('Minimum 16 characters', 'wp2-update')}" required autocomplete="off" />
            </label>
            <label class="wp2-label">${__('App Name', 'wp2-update')}
                <input type="text" name="wp2_app_name" class="wp2-input" required />
            </label>
            <label class="wp2-label">${__('App ID', 'wp2-update')}
                <input type="text" name="wp2_app_id" class="wp2-input" required />
            </label>
            <label class="wp2-label">${__('Installation ID', 'wp2-update')}
                <input type="text" name="wp2_installation_id" class="wp2-input" required />
            </label>
            <label class="wp2-label">${__('Webhook Secret', 'wp2-update')}
                <input type="password" name="wp2_webhook_secret" class="wp2-input" required />
            </label>
            <label class="wp2-label">${__('Private Key (.pem)', 'wp2-update')}
                <textarea name="wp2_private_key" class="wp2-input wp2-code" rows="10" required></textarea>
            </label>
            <div class="wp2-form-actions">
                <button type="button" id="wp2-cancel-config" class="wp2-button wp2-button-secondary">${__('Cancel', 'wp2-update')}</button>
                <button type="submit" class="wp2-button wp2-button-primary">${__('Save Credentials', 'wp2-update')}</button>
            </div>
        </form>
    </section>
`;

export const onSubmitManualForm = async (event, fetchConnectionStatus) => {
    event.preventDefault();
    const toast = await ensureToast();
    const form = event.currentTarget;
    const submitButton = form.querySelector('button[type="submit"]');
    const formData = new FormData(form);
    const body = Object.fromEntries(formData.entries());

    try {
        show_global_spinner();
        submitButton.disabled = true;
        await api_request('save-credentials', { method: 'POST', body });
        toast('Credentials saved successfully.', 'success');
        await fetchConnectionStatus();
    } catch (error) {
        console.error('Failed to save credentials', error);
        toast('Failed to save credentials.', 'error', error.message);
    } finally {
        submitButton.disabled = false;
        hide_global_spinner();
    }
};
