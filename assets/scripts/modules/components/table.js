import { app_state } from '../state/store.js';

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
    const parse = (v) => v.split('.').map(Number);
    const [a, b] = [parse(normalize_version(v1)), parse(normalize_version(v2))];

    for (let i = 0; i < Math.max(a.length, b.length); i++) {
        const [x, y] = [a[i] || 0, b[i] || 0];
        if (x !== y) {
            return operator === '>' ? x > y : x < y;
        }
    }
    return false;
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

	return `
<tr id="package-row-${escape_html(pkg.repo.replace('/', '-'))}" class="${isUpdating ? 'updating' : ''} ${isError ? 'error' : ''}">
	<td class="package-name">
		<strong>${escape_html(pkg.name)}</strong><br>
		<a href="https://github.com/${escape_html(pkg.repo)}" target="_blank" rel="noopener noreferrer">${escape_html(pkg.repo)}</a>
	</td>
	<td class="package-version">
		${pkg.installed ? `v${escape_html(pkg.installed)}` : `<span class="description">${t.notInstalled}</span>`}
	</td>
	<td class="package-releases">
		${!hasReleases
			? `<span class="description">${t.noReleasesFound}</span>`
			: `<select class="release-dropdown" data-package-repo="${escape_html(pkg.repo)}" ${isUpdating ? 'disabled' : ''}>
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
	<td class="package-actions">
		${isError
			? err_badge(pkg.errorMessage)
			: `<button class="button button-primary" data-action="update-package" data-package-repo="${escape_html(pkg.repo)}" data-package-type="${escape_html(pkg.type || '')}" ${isUpdating ? 'disabled' : ''}>${actionLabel}</button>`}
	</td>
</tr>`;
};

/**
 * @param {Array<any>} packages
 * @param {boolean} isLoading
 */
export const render_package_table = (packages, isLoading) => {
	const tbody = document.querySelector('#wp2-package-table tbody');
	if (!tbody) return;
	tbody.setAttribute('aria-live', 'polite');

	if (isLoading) {
		tbody.innerHTML = `<tr><td colspan="4" class="loading-message">${t.loadingPackages}</td></tr>`;
		return;
	}
	if (!packages || packages.length === 0) {
		tbody.innerHTML = `<tr><td colspan="4" class="empty-message">${t.noRepositories}</td></tr>`;
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
	<button id="wp2-retry-sync" class="button" style="margin-left:10px">Retry</button>
</td>`;
		tbody.appendChild(tr);
		const retry = tr.querySelector('#wp2-retry-sync');
		if (retry) retry.addEventListener('click', () => {
			app_state.set({ ...app_state.get(), syncError: null });
			document.querySelector('[data-action="sync-packages"]')?.click();
		});
	}
};
