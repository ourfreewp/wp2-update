const { __ } = wp.i18n;
import { escapeHtml } from '../../utils/string.js';

/**
 * Renders a standardized action button.
 * @param {string} action - The `data-action` attribute value.
 * @param {string} label - The button text.
 * @param {string} icon - The emoji or icon for the button.
 * @returns {string} The HTML for the button.
 */
export const ActionButton = (action, label, icon) => {
    return `
		<button type="button" class="wp2-btn wp2-btn--ghost" data-action="${action}">
			<span class="wp2-icon">${icon}</span>
			${escapeHtml(__(label, 'wp2-update'))}
		</button>
	`;
};
