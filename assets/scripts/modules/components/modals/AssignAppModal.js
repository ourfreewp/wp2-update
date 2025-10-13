import { escapeHtml } from '../../utils/string.js';
import { store } from '../../state/store.js';

export const AssignAppModal = (pkg) => {
    const { apps } = store.get();
    return `
        <div class="wp2-modal-header">
            <h2>Assign App to ${escapeHtml(pkg.name)}</h2>
        </div>
        <div class="wp2-modal-body">
            <form class="wp2-form" data-repo="${escapeHtml(pkg.repo)}">
                <p>Select a GitHub App to manage this package.</p>
                <select name="app_id" class="wp2-input">
                    ${apps.map(app => `<option value="${app.id}">${escapeHtml(app.name)}</option>`).join('')}
                </select>
                <div class="wp2-modal-actions">
                    <button type="submit" class="wp2-btn wp2-btn--primary">Assign App</button>
                </div>
            </form>
        </div>
    `;
};
