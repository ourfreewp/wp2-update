import { escapeHtml } from '../../utils.js';
import { renderActionButton } from './renderActionButton.js';

export const renderAppRow = (app = {}, packages = []) => {
	const managedPackages = packages.filter(pkg => (pkg.app_id ?? pkg.app_uid) === app.id);
	return `
		<tr data-wp2-app-row="${escapeHtml(app.id || '')}">
			<td>
				<div class="wp2-table-cell__title">${escapeHtml(app.name || 'Untitled App')}</div>
				<div class="wp2-table-cell__subtitle">${escapeHtml(app.slug || '')}</div>
			</td>
			<td>${escapeHtml((app.account_type || 'user').toString())}</td>
			<td>${managedPackages.length}</td>
			<td class="wp2-table-cell__actions">
				${renderActionButton('app-details', 'View', '🔍')}
			</td>
		</tr>
	`;
};