import { escapeHtml } from '../../utils/string.js';

export const PackageDetailsModal = (pkg) => `
    <div class="wp2-modal-header">
        <h2>${escapeHtml(pkg.name)}</h2>
    </div>
    <div class="wp2-modal-body">
        <dl class="wp2-detail-grid">
            <dt>Repository</dt><dd>${escapeHtml(pkg.repo)}</dd>
            <dt>Installed Version</dt><dd>${escapeHtml(pkg.version || 'N/A')}</dd>
            <dt>Latest Version</dt><dd>${escapeHtml(pkg.latest || 'N/A')}</dd>
        </dl>
    </div>
`;
