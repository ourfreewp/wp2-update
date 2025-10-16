const { __ } = wp.i18n;
import { escapeHtml } from '../../utils/string.js';
import { store } from '../../state/store.js';
import { StandardModal } from './StandardModal.js';

export const AssignAppModal = (pkg) => {
    const { apps } = store.get();

    const bodyContent = `
        <form class="wp2-form" data-repo="${escapeHtml(pkg.repo)}">
            <p>${wp2UpdateData.i18n.selectGitHubApp}</p>
            <select name="app_id" class="wp2-input">
                ${apps.map(app => `<option value="${app.id}">${escapeHtml(app.name)}</option>`).join('')}
            </select>
        </form>
    `;

    const footerActions = [
        { label: 'Cancel', class: 'wp2-btn--secondary', attributes: 'data-dismiss="modal"' },
        { label: 'Assign App', class: 'wp2-btn--primary', attributes: 'type="submit"' }
    ];

    return StandardModal({
        title: `${wp2UpdateData.i18n.assignAppTitle} ${escapeHtml(pkg.name)}`,
        bodyContent,
        footerActions
    });
};
