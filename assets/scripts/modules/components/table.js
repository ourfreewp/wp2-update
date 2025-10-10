import { app_state } from '../state/store.js';
import semver from 'semver';

const { __ } = wp.i18n;

const escape_html = (str) =>
	String(str).replace(/[&<>'"`]/g, (match) => ({
		'&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;', '`': '&#96;',
	}[match]));

const t = {
	noReleasesFound: __('No releases found.', 'wp2-update'),
	notInstalled: __('Not Installed', 'wp2-update'),
	loadingPackages: __('Loading packages, please wait...', 'wp2-update'),
	noRepositories: __('No repositories found. Please ensure your GitHub App has been granted access to the correct repositories on GitHub.com.', 'wp2-update'),
	update: __('Update', 'wp2-update'),
};

const err_badge = (msg) => {
    const message = msg || __('An error occurred.', 'wp2-update');
    return `<span class="error-message">${escape_html(message)}</span>`;
};

const normalize_version = (version) => {
    if (!version) return '0.0.0';
    return version.replace(/^v/, '');
};

const versionCompare = (v1, v2, operator) => {
    if (!semver.valid(v1) || !semver.valid(v2)) {
        return false;
    }

    switch (operator) {
        case '>':
            return semver.gt(v1, v2);
        case '<':
            return semver.lt(v1, v2);
        case '==':
            return semver.eq(v1, v2);
        default:
            return false;
    }
};

/**
 * @param {any} pkg
 */
const row_html = (pkg) => {
    const isUpdating = pkg.status === 'updating';
    const isError = pkg.status === 'error';
    const releases = Array.isArray(pkg.releases) ? pkg.releases : [];
    if (!releases.length && pkg.latest_release) {
        releases.push(pkg.latest_release);
    }
    const hasReleases = releases.length > 0;

    const installedVersion = pkg.installed || '';
    const selectedVersion = releases[0]?.tag_name || '';
    const actionLabel = versionCompare(installedVersion, selectedVersion, '>')
        ? __('Rollback', 'wp2-update')
        : (pkg.installed ? __('Update', 'wp2-update') : __('Install', 'wp2-update'));

    const actionType = versionCompare(installedVersion, selectedVersion, '>')
        ? 'rollbackPackage'
        : (pkg.installed ? 'updatePackage' : 'installPackage');

    return `
        <tr class="wp2-table-row">
            <td class="wp2-table-cell wp2-package-name" data-label="${__('Package', 'wp2-update')}">
                <strong>${escape_html(pkg.name)}</strong><br>
                <a href="https://github.com/${escape_html(pkg.repo)}" target="_blank" rel="noopener noreferrer">${escape_html(pkg.repo)}</a>
            </td>
            <td class="wp2-table-cell wp2-package-version" data-label="${__('Installed', 'wp2-update')}">
                ${pkg.installed ? `v${escape_html(pkg.installed)}` : `<span class="wp2-text-subtle">${t.notInstalled}</span>`}
            </td>
            <td class="wp2-table-cell wp2-package-releases" data-label="${__('Available Version', 'wp2-update')}">
                ${!hasReleases
                    ? `<span class="wp2-text-subtle">${t.noReleasesFound}</span>`
                    : `<select class="wp2-select wp2-release-dropdown" data-package-repo="${escape_html(pkg.repo)}" ${isUpdating ? 'disabled' : ''}>
                        ${releases.map(rel => {
                            const rawTag = rel?.tag_name || '';
                            const tag = escape_html(rawTag);
                            const rawLabel = rel?.name || rawTag || '';
                            const label = rawLabel ? escape_html(rawLabel) : t.noReleasesFound;
                            const suffix = rel?.prerelease ? ` ${escape_html(__('(Pre-release)', 'wp2-update'))}` : '';
                            return `<option value="${tag}">${label}${suffix}</option>`;
                        }).join('')}
                      </select>`}
            </td>
            <td class="wp2-table-cell wp2-package-actions" data-label="${__('Actions', 'wp2-update')}">
                <button class="wp2-button wp2-button-primary" data-wp2-action="${actionType}" data-package-repo="${escape_html(pkg.repo)}" ${isUpdating ? 'disabled' : ''}>
                    ${isUpdating ? `<span class="wp2-spinner"></span>` : escape_html(actionLabel)}
                </button>
                ${isError ? err_badge(pkg.error) : ''}
            </td>
        </tr>
    `;
};

/**
 * @param {Array<any>} packages
 * @param {boolean} isLoading
 */
export const render_package_table = (packages, isLoading) => {
	const tbody = document.querySelector('.wp2-table-body');
	if (!tbody) return;
	tbody.setAttribute('aria-live', 'polite');

	if (isLoading) {
		tbody.innerHTML = `<tr><td colspan="4" class="wp2-loading-message">${t.loadingPackages}</td></tr>`;
		return;
	}
	if (!packages || packages.length === 0) {
		tbody.innerHTML = `<tr><td colspan="4" class="wp2-empty-message">${t.noRepositories}</td></tr>`;
		return;
	}

	tbody.innerHTML = packages.map(row_html).join('');

	// Inline sync error row (if any)
	const s = app_state.get();
	if (s.syncError) {
		const tr = document.createElement('tr');
		tr.innerHTML = `
<td colspan="4" style="color:red;text-align:center;">
	${escape_html(s.syncError)}
	<button id="wp2-retry-sync" class="wp2-button" style="margin-left:10px">Retry</button>
</td>`;
		tbody.appendChild(tr);
		const retry = tr.querySelector('#wp2-retry-sync');
		if (retry) retry.addEventListener('click', () => {
			app_state.set({ ...app_state.get(), syncError: null });
			document.querySelector('[data-action="wp2-sync-packages"]')?.click();
		});
	}
};
