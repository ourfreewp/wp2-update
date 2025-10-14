import { escapeHtml } from '../../utils/string.js';
import { ActionButton } from '../ui/Button.js';

/**
 * Renders a single row for the apps table.
 * @param {object} app - The app data.
 * @param {Array} packages - The list of all packages to count managed ones.
 * @returns {string} The HTML for the table row.
 */
export const AppRow = (app, packages) => {
    const managedPackagesCount = packages.filter(pkg => pkg.app_id === app.id).length;
    return `
		<tr data-app-id="${escapeHtml(app.id)}">
			<td>
				<div class="wp2-table-cell__title">${escapeHtml(app.name)}</div>
				<div class="wp2-table-cell__subtitle">${escapeHtml(app.slug)}</div>
			</td>
			<td>${escapeHtml(app.account_type)}</td>
			<td>${managedPackagesCount}</td>
			<td class="wp2-table-cell__actions">
				${ActionButton('open-app-details', 'View', '🔍')}
			</td>
		</tr>
	`;
};
