import { escapeHtml } from '../../utils/string.js';
import { StatusBadge } from './StatusBadge.js';
import { ReleaseDropdown } from './ReleaseDropdown.js';
import { PackageActions } from './PackageActions.js';
import { Dropdown } from '../Dropdown.js';

/**
 * Renders a single row for the packages table.
 * @param {object} pkg - The package data.
 * @returns {string} The HTML for the table row.
 */
export const PackageRow = (pkg) => {
    return `
        <tr data-repo="${escapeHtml(pkg.repo)}">
            <td>
                <div class="wp2-table-cell__title">${escapeHtml(pkg.name)}</div>
                <div class="wp2-table-cell__subtitle">${escapeHtml(pkg.repo)}</div>
            </td>
            <td>${StatusBadge(pkg)}</td>
            <td>${escapeHtml(pkg.version || 'N/A')}</td>
            <td>${ReleaseDropdown(pkg.releases || [], pkg.version)}</td>
            <td class="wp2-table-cell__actions">
                ${PackageActions(pkg)}
            </td>
        </tr>
    `;
};
