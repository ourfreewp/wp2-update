import { escapeHtml } from '../../utils/string.js';
import { ReleaseDropdown } from '../ReleaseDropdown.js';

export const RollbackModal = (pkg) => `
    <div class="wp2-modal-header">
        <h2>Rollback ${escapeHtml(pkg.name)}</h2>
    </div>
    <div class="wp2-modal-body">
        <form class="wp2-form" data-repo="${escapeHtml(pkg.repo)}">
            <p>Select a version to rollback to.</p>
            ${ReleaseDropdown(pkg.releases, pkg.version)}
            <div class="wp2-modal-actions">
                <button type="submit" class="wp2-btn wp2-btn--danger">Confirm Rollback</button>
            </div>
        </form>
    </div>
`;
