const { __ } = wp.i18n;
import { apiFetch } from '../../utils/apiFetch.js';
import { escapeHtml } from '../../utils/string.js';
import { ReleaseDropdown } from '../package/ReleaseDropdown.js';
import { StandardModal } from './StandardModal.js';

export const RollbackModal = (pkg) => {
    const bodyContent = `
        <form class="wp2-form" data-repo="${escapeHtml(pkg.repo)}">
            <p>${wp2UpdateData.i18n.rollbackSelectVersion}</p>
            ${ReleaseDropdown(pkg.releases, pkg.version)}
        </form>
    `;

    const footerActions = [
        { label: wp2UpdateData.i18n.rollbackCancel, class: 'wp2-btn--secondary', attributes: 'data-dismiss="modal"' },
        { label: wp2UpdateData.i18n.rollbackConfirm, class: 'wp2-btn--danger', attributes: 'type="submit"' }
    ];

    return StandardModal({
        title: `${wp2UpdateData.i18n.rollbackTitle} ${escapeHtml(pkg.name)}`,
        bodyContent,
        footerActions
    });
};

export async function initializeRollbackModal(version, repoSlug) {
    const rollbackButton = document.getElementById('rollback-button');

    rollbackButton.addEventListener('click', async () => {
        try {
            await apiFetch({
                path: '/wp2-update/v1/packages/action',
                method: 'POST',
                data: { version, repo_slug: repoSlug, action: 'rollback' },
            });

            alert(wp2UpdateData.i18n.rollbackSuccess);
        } catch (error) {
            alert(wp2UpdateData.i18n.rollbackFailed);
        }
    });
}
