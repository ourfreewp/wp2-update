import { escapeHtml } from '../utils/string.js';

/**
 * Renders a status badge for a package.
 * @param {object} pkg - The package data.
 * @returns {string} The HTML for the status badge.
 */
export const StatusBadge = (pkg) => {
    const isManaged = pkg.is_managed ?? Boolean(pkg.app_id);
    const hasUpdate = isManaged && pkg.installed && pkg.latest && pkg.installed !== pkg.latest;

    if (!isManaged) {
        return `<span class="wp2-status-badge wp2-status-badge--unmanaged">Unmanaged</span>`;
    }

    if (hasUpdate) {
        return `<span class="wp2-status-badge wp2-status-badge--update">Update Available</span>`;
    }

    return `<span class="wp2-status-badge wp2-status-badge--ok">Up to Date</span>`;
};
