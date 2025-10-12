import { STATUS } from '../../state/store.js';
import { escapeHtml } from '../../utils.js';

const { __ } = window.wp?.i18n ?? { __: (text) => text };

const formatDetailsList = (details = {}) => {
	const entries = Object.entries(details).filter(([, value]) => value !== undefined && value !== null && value !== '');
	if (!entries.length) {
		return `<p class="wp2-empty-state">${escapeHtml(__('No additional details available yet.', 'wp2-update'))}</p>`;
	}

	return `
		<dl class="wp2-definition-list">
			${entries
				.map(
					([key, value]) => `
				<div class="wp2-definition-list__item">
					<dt>${escapeHtml(String(key).replace(/_/g, ' '))}</dt>
					<dd>${escapeHtml(typeof value === 'string' ? value : JSON.stringify(value))}</dd>
				</div>
			`
				)
				.join('')}
		</dl>
	`;
};

const statusLabel = (status) => {
	switch (status) {
		case STATUS.LOADING:
			return __('Loading…', 'wp2-update');
		case STATUS.NOT_CONFIGURED:
			return __('Not Configured', 'wp2-update');
		case STATUS.NOT_CONFIGURED_WITH_PACKAGES:
			return __('Not Configured (packages detected)', 'wp2-update');
		case STATUS.CONFIGURING:
			return __('Configuring', 'wp2-update');
		case STATUS.MANUAL_CONFIGURING:
			return __('Manual Configuration', 'wp2-update');
		case STATUS.APP_CREATED:
			return __('App Created – awaiting installation', 'wp2-update');
		case STATUS.CONNECTING:
			return __('Connecting…', 'wp2-update');
		case STATUS.INSTALLED:
			return __('Connected', 'wp2-update');
		case STATUS.ERROR:
			return __('Error', 'wp2-update');
		default:
			return __('Unknown', 'wp2-update');
	}
};

export const settingsView = (state) => {
	const statusSummary = `
		<div class="wp2-status-summary">
			<p><strong>${__('Connection status:', 'wp2-update')}</strong> ${escapeHtml(statusLabel(state.status))}</p>
			${state.message ? `<p>${escapeHtml(state.message)}</p>` : ''}
			<div class="wp2-status-actions">
				<button type="button" id="wp2-refresh-status" class="wp2-btn wp2-btn--ghost">
					${__('Refresh Status', 'wp2-update')}
				</button>
				<button type="button" id="wp2-open-manual-setup" class="wp2-btn wp2-btn--primary-outline">
					${__('Enter Credentials Manually', 'wp2-update')}
				</button>
			</div>
		</div>
	`;

	const detailsSection = `
		<div class="wp2-panel-section">
			<header class="wp2-panel-header">
				<h2>${__('Connection Details', 'wp2-update')}</h2>
			</header>
			${formatDetailsList(state.details)}
		</div>
	`;

	return `
		<div class="wp2-panel-section">
			<header class="wp2-panel-header">
				<h2>${__('Status Overview', 'wp2-update')}</h2>
			</header>
			${statusSummary}
		</div>
		${detailsSection}
	`;
};
