const { __ } = wp.i18n;
import { escapeHtml } from '../../utils/string.js';
import { ReleaseDropdown } from '../package/ReleaseDropdown.js';

export const RollbackModal = (pkg) => `
    <div class="wp2-modal-header">
        <h2>${__('Rollback', 'wp2-update')} ${escapeHtml(pkg.name)}</h2>
    </div>
    <div class="wp2-modal-body">
        <form class="wp2-form" data-repo="${escapeHtml(pkg.repo)}">
            <p>${__('Select a version to rollback to.', 'wp2-update')}</p>
            ${ReleaseDropdown(pkg.releases, pkg.version)}
            <div class="wp2-modal-actions">
                <button type="submit" class="wp2-btn wp2-btn--danger">${__('Confirm Rollback', 'wp2-update')}</button>
            </div>
        </form>
    </div>
`;
