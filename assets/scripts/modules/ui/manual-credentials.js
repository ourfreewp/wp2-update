import { api_request } from '../api';
import { show_global_spinner, hide_global_spinner } from './spinner';
import { toast } from './toast';

export function renderManualCredentialsForm() {
    const container = document.getElementById('wp2-manual-credentials');
    if (!container) return;

    container.innerHTML = `
        <form id="manual-credentials-form">
            <label>
                App Name: <input type="text" name="wp2_app_name" required />
            </label>
            <label>
                App ID: <input type="number" name="wp2_app_id" required />
            </label>
            <label>
                Installation ID: <input type="number" name="wp2_installation_id" required />
            </label>
            <label>
                Private Key: <textarea name="wp2_private_key" required></textarea>
            </label>
            <label>
                Webhook Secret: <input type="text" name="wp2_webhook_secret" required />
            </label>
            <label>
                Encryption Key: <input type="text" name="encryption_key" required />
            </label>
            <button type="submit">Save Credentials</button>
        </form>
    `;

    const form = document.getElementById('manual-credentials-form');
    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const formData = new FormData(form);
        const body = Object.fromEntries(formData.entries());

        try {
            show_global_spinner();
            await api_request('save-credentials', { method: 'POST', body });
            toast('Credentials saved successfully.', 'success');
        } catch (error) {
            console.error('Failed to save credentials', error);
            toast('Failed to save credentials.', 'error');
        } finally {
            hide_global_spinner();
        }
    });

    container.hidden = false;
}