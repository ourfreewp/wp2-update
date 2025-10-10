import { escapeHTML } from '../../../utils.js';

const { __ } = window.wp?.i18n ?? { __: (text) => text };

const renderPackageRow = (pkg) => {
    const installed = pkg.installed || '—';
    const releases = Array.isArray(pkg.releases) ? pkg.releases : [];
    const latest = releases[0]?.tag_name?.replace(/^v/i, '') || null;
    const installedVersion = installed.replace(/^v/i, '');
    const hasUpdate = latest && installedVersion && latest !== installedVersion;
    const actionLabel = hasUpdate ? __('Update', 'wp2-update') : __('Re-install', 'wp2-update');

    const releaseOptions = releases.map(rel => `
        <option value="${escapeHTML(rel.tag_name)}">${escapeHTML(rel.name || rel.tag_name)}</option>
    `).join('');

    return `
        <tr data-repo="${escapeHTML(pkg.repo)}">
            <td>
                <strong>${escapeHTML(pkg.name || pkg.repo)}</strong>
                ${pkg.repo ? `<div class="wp2-muted"><code>${escapeHTML(pkg.repo)}</code></div>` : ''}
            </td>
            <td>${escapeHTML(installed)}</td>
            <td>
                <select class="wp2-select wp2-release-select">
                    ${releaseOptions}
                </select>
            </td>
            <td>
                <button type="button" class="wp2-button wp2-button-${hasUpdate ? 'primary' : 'secondary'} wp2-package-action">
                    ${actionLabel}
                </button>
            </td>
        </tr>
    `;
};

export const managedPackagesTable = (packages) => `
    <div class="wp2-table-wrapper">
        <table class="wp2-table">
            <thead>
                <tr>
                    <th>${__('Package', 'wp2-update')}</th>
                    <th>${__('Installed', 'wp2-update')}</th>
                    <th>${__('Available Releases', 'wp2-update')}</th>
                    <th>${__('Action', 'wp2-update')}</th>
                </tr>
            </thead>
            <tbody>
                ${packages.map(renderPackageRow).join('')}
            </tbody>
        </table>
    </div>
`;
