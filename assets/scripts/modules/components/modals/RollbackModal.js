const { __ } = wp.i18n;
import { api_request } from '../../api.js';
import { escapeHtml } from '../../utils/string.js';
import { ReleaseDropdown } from '../package/ReleaseDropdown.js';
import { StandardModal } from './StandardModal.js';

export const RollbackModal = (pkg) => {
    const bodyContent = `
        <form class="wp2-form" data-repo="${escapeHtml(pkg.repo)}">
            <p>${__('Select a version to rollback to.', 'wp2-update')}</p>
            ${ReleaseDropdown(pkg.releases, pkg.version)}
        </form>
    `;

    const footerActions = [
        { label: __('Cancel', 'wp2-update'), class: 'wp2-btn--secondary', attributes: 'data-dismiss="modal"' },
        { label: __('Confirm Rollback', 'wp2-update'), class: 'wp2-btn--danger', attributes: 'type="submit"' }
    ];

    return StandardModal({
        title: `${__('Rollback', 'wp2-update')} ${escapeHtml(pkg.name)}`,
        bodyContent,
        footerActions
    });
};

export async function initializeRollbackModal(version, repoSlug) {
    const rollbackButton = document.getElementById('rollback-button');

    rollbackButton.addEventListener('click', async () => {
        try {
            await api_request('packages/action', {
                method: 'POST',
                body: JSON.stringify({ version, repo_slug: repoSlug, action: 'rollback' })
            }, 'wp2_package_action');

            alert(__('Rollback successful!', 'wp2-update'));
        } catch (error) {
            alert(__('Failed to rollback. Please try again.', 'wp2-update'));
        }
    });
}
