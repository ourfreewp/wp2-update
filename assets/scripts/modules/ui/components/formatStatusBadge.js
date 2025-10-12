import { escapeHtml } from '../../utils.js';

export const formatStatusBadge = (pkg) => {
	const isManaged = pkg.is_managed ?? Boolean(pkg.app_id);
	const installed = pkg.installed || pkg.version || '';
	const latest = pkg.latest || pkg.github_data?.latest_release || '';
	const hasUpdate = isManaged && installed && latest && installed !== latest;

	if (!isManaged) {
		return `
			<span class="wp2-status-badge wp2-status-badge--unmanaged">
				<span class="wp2-status-dot wp2-status-dot--unmanaged"></span>
				${escapeHtml('Unmanaged')}
			</span>
		`;
	}

	if (hasUpdate) {
		return `
			<span class="wp2-status-badge wp2-status-badge--update">
				<span class="wp2-status-dot wp2-status-dot--update"></span>
				${escapeHtml('Update available')}
			</span>
		`;
	}

	return `
		<span class="wp2-status-badge wp2-status-badge--ok">
			<span class="wp2-status-dot wp2-status-dot--ok"></span>
			${escapeHtml('Up to date')}
		</span>
	`;
};