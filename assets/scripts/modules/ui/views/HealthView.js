import { api_request } from '../../api.js';
import { escapeHtml } from '../../utils.js';

const renderHealthSection = (title, data) => {
    return `
        <div class="wp2-dashboard-card">
            <h3>${escapeHtml(title)}</h3>
            <dl class="wp2-detail-grid">
                ${Object.entries(data).map(([key, value]) => `
                    <div class="wp2-detail-item">
                        <dt>${escapeHtml(key.replace(/_/g, ' '))}</dt>
                        <dd>${escapeHtml(value)}</dd>
                    </div>
                `).join('')}
            </dl>
        </div>
    `;
};

export const HealthView = {
    init() {
        const healthPanel = document.getElementById('wp2-health-panel');
        if (!healthPanel) return;

        // Add interactivity to the server-rendered content
        const refreshButton = document.createElement('button');
        refreshButton.className = 'wp2-btn wp2-btn--primary';
        refreshButton.textContent = 'Refresh Status';
        refreshButton.addEventListener('click', this.refresh);

        healthPanel.prepend(refreshButton);
    },

    async refresh() {
        const healthPanel = document.querySelector('.wp2-health-view');
        if (!healthPanel) return;

        try {
            const healthStatus = await api_request('health', { method: 'GET' });
            healthPanel.innerHTML = Object.values(healthStatus.data).map(renderHealthSection).join('');
            toast('Health status refreshed successfully.', 'success');
        } catch (error) {
            console.error('Failed to refresh health status', error);
            toast('Failed to refresh health status. Please try again.', 'error');
        }
    },
};