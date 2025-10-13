import { api_request } from '../api.js';
import { escapeHtml } from '../utils/string.js';
import { NotificationService } from '../services/NotificationService.js';
import { __ } from '@wordpress/i18n';

export class HealthView {
    /**
     * Renders the health status view.
     * @param {HTMLElement} rootElement - The element to render into.
     */
    async render(rootElement) {
        rootElement.innerHTML = `<h3>${__("Loading Health Status...", "wp2-update")}</h3>`;
        try {
            const response = await api_request('health', { method: 'GET' });
            const healthData = response.data || {};
            rootElement.innerHTML = `
                <header class="wp2-panel-header"><h2>${__("System Health", "wp2-update")}</h2></header>
                <dl class="wp2-detail-grid">
                    <dt>${__("PHP Version", "wp2-update")}</dt><dd>${escapeHtml(healthData.php_version || __("N/A", "wp2-update"))}</dd>
                    <dt>${__("Database Status", "wp2-update")}</dt><dd>${escapeHtml(healthData.db_status || __("N/A", "wp2-update"))}</dd>
                </dl>
            `;
        } catch (error) {
            rootElement.innerHTML = `<h3>${__("Could not load health status.", "wp2-update")}</h3>`;
            NotificationService.showError(__("Failed to fetch health status.", "wp2-update"));
        }
    }
}
