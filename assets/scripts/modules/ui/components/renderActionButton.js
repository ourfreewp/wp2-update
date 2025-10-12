import { escapeHtml } from '../../utils.js';

export const renderActionButton = (action, label, icon) => {
	return `
		<button type="button" class="wp2-btn wp2-btn--ghost" data-wp2-action="${action}">
			<span class="wp2-icon">${icon}</span>
			${escapeHtml(label)}
		</button>
	`;
};