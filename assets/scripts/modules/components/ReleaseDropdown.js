import { escapeHtml } from '../utils/string.js';

/**
 * Renders a dropdown of available releases for a package.
 * @param {Array} releases - The list of release objects.
 * @param {string} currentVersion - The currently selected version.
 * @returns {string} The HTML for the select dropdown.
 */
export const ReleaseDropdown = (releases = [], currentVersion) => {
    if (!releases.length) {
        return '<span>No releases</span>';
    }
    return `
		<select class="wp2-release-dropdown">
			${releases.map(release => `
				<option value="${escapeHtml(release.tag_name)}" ${release.tag_name === currentVersion ? 'selected' : ''}>
					${escapeHtml(release.name || release.tag_name)}
				</option>
			`).join('')}
		</select>
	`;
};
