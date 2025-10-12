import { escapeHtml } from '../../utils.js';

export const renderReleaseDropdown = (releases, currentVersion) => {
	return `
		<select class="wp2-release-dropdown">
			${releases.map(release => `
				<option value="${release.tag_name}" ${release.tag_name === currentVersion ? 'selected' : ''}>
					${release.tag_name}
				</option>
			`).join('')}
		</select>
	`;
};