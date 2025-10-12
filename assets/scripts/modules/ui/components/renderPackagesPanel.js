import { renderReleaseDropdown } from './renderReleaseDropdown.js';
import { renderPackageActions } from './renderPackageActions.js';
import { escapeHtml } from '../../utils.js';

export const renderPackagesPanel = (packages = [], apps = []) => {
	if (!packages.length) {
		return `<p>${escapeHtml('No packages available.')}</p>`;
	}

	return `
		<table class="wp2-table">
			<thead>
				<tr>
					<th>${escapeHtml('Package Name')}</th>
					<th>${escapeHtml('Version')}</th>
					<th>${escapeHtml('Release')}</th>
					<th>${escapeHtml('Actions')}</th>
				</tr>
			</thead>
			<tbody>
				${packages.map(pkg => `
					<tr>
						<td>${escapeHtml(pkg.name)}</td>
						<td>${escapeHtml(pkg.version || 'N/A')}</td>
						<td>${renderReleaseDropdown(pkg.releases || [], pkg.version)}</td>
						<td>${renderPackageActions(pkg)}</td>
					</tr>
				`).join('')}
			</tbody>
		</table>
	`;
};