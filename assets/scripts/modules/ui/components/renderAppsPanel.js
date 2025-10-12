import { renderAppRow } from './renderAppRow.js';
import { escapeHtml } from '../../utils.js';

export const renderAppsPanel = (apps = [], packages = []) => {
	if (!apps.length) {
		return `
			<div class="wp2-welcome-view">
				<h1>Welcome to WP2 Update</h1>
				<p>Get started by connecting a GitHub App to manage your plugins and themes.</p>
				<ul>
					<li>Connect your GitHub App to sync repositories.</li>
					<li>Manage updates for plugins and themes directly from your dashboard.</li>
					<li>Stay secure with action-specific nonces and detailed error handling.</li>
				</ul>
				<button type="button" class="wp2-btn wp2-btn--primary" data-wp2-action="open-wizard">Get Started</button>
			</div>
		`;
	}

	return `
		<div class="wp2-table-wrapper">
			<table class="wp2-table" data-wp2-table="apps">
				<thead>
					<tr>
						<th>${escapeHtml('App Name')}</th>
						<th>${escapeHtml('Account Type')}</th>
						<th>${escapeHtml('Packages')}</th>
						<th class="wp2-table-cell__actions">${escapeHtml('Actions')}</th>
					</tr>
				</thead>
				<tbody>
					${apps.map(app => renderAppRow(app, packages)).join('')}
				</tbody>
			</table>
		</div>
	`;
};