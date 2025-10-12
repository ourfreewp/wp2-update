import { AppsTable } from '../components/tables/AppsTable.js';
import { AddAppWizard } from '../wizards/AddAppWizard.js';
import { STATUS } from '../../state/store.js';
import { escapeHtml } from '../../utils.js';

const { __ } = window.wp?.i18n ?? { __: (text) => text };

export const appsView = (state) => {
	const apps = Array.isArray(state.apps) ? state.apps : [];
	const hasApps = apps.length > 0;
	const statusMessage = (() => {
		if (state.status === STATUS.NOT_CONFIGURED || state.status === STATUS.NOT_CONFIGURED_WITH_PACKAGES) {
			return __('Connect a GitHub App to start managing packages.', 'wp2-update');
		}
		if (state.status === STATUS.APP_CREATED) {
			return __('Waiting for the GitHub App installation to complete.', 'wp2-update');
		}
		return '';
	})();

	const appsSection = hasApps
		? AppsTable(apps)
		: `<div class="wp2-empty-state">
				<p>${escapeHtml(__('No GitHub Apps found yet. Click the button below to add one.', 'wp2-update'))}</p>
		   </div>`;

	return `
		<div class="wp2-panel-section">
			<header class="wp2-panel-header">
				<div>
					<h2>${__('Configured Apps', 'wp2-update')}</h2>
					${statusMessage ? `<p class="wp2-panel-subtitle">${escapeHtml(statusMessage)}</p>` : ''}
				</div>
				<button id="wp2-refresh-apps" type="button" class="wp2-btn wp2-btn--ghost">
					${__('Refresh', 'wp2-update')}
				</button>
			</header>
			${appsSection}
		</div>
		<div class="wp2-panel-section">
			${AddAppWizard()}
		</div>
	`;
};
