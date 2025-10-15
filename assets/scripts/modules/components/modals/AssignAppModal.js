const { __ } = wp.i18n;
import { escapeHtml } from '../../utils/string.js';
import { store } from '../../state/store.js';
import { StandardModal } from './StandardModal.js';

export const AssignAppModal = (pkg) => {
    const { apps } = store.get();

    const bodyContent = `
        <form class="wp2-form" data-repo="${escapeHtml(pkg.repo)}">
            <p>${__('Select a GitHub App to manage this package.', 'wp2-update')}</p>
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
        title: `${__('Assign App to', 'wp2-update')} ${escapeHtml(pkg.name)}`,
        bodyContent,
        footerActions
    });
};
