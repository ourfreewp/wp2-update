import { PackagesTable } from '../components/tables/PackagesTable.js';
import { escapeHtml } from '../../utils.js';

const { __ } = window.wp?.i18n ?? { __: (text) => text };

export const PackagesView = (state = {}) => {
	const packages = Array.isArray(state.packages) ? state.packages : [];

	if (packages.length === 0) {
		return `
			<div class="wp2-panel-section">
				<header class="wp2-panel-header">
					<div>
						<h2>${escapeHtml(__('All Packages', 'wp2-update'))}</h2>
						<p class="wp2-panel-subtitle">${escapeHtml(__('Manage both managed and unassigned packages detected across your connected sites.', 'wp2-update'))}</p>
					</div>
				</header>
				<div class="wp2-empty-state">
					<p>${escapeHtml(__('No packages found. Try refreshing or syncing your data.', 'wp2-update'))}</p>
				</div>
			</div>
		`;
	}

	return `
		<div class="wp2-panel-section">
			<header class="wp2-panel-header">
				<div>
					<h2>${escapeHtml(__('All Packages', 'wp2-update'))}</h2>
					<p class="wp2-panel-subtitle">${escapeHtml(__('Manage both managed and unassigned packages detected across your connected sites.', 'wp2-update'))}</p>
				</div>
				<div class="wp2-status-actions">
					<button type="button" class="wp2-btn wp2-btn--ghost" data-wp2-action="refresh-packages">${escapeHtml(__('Refresh', 'wp2-update'))}</button>
				</div>
			</header>
			<div class="wp2-table-container">
				${PackagesTable(packages)}
			</div>
		</div>
	`;
};
