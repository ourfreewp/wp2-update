import { escapeHtml } from '../../../utils.js';

/**
 * Render a table for displaying packages.
 * @param {Array} packages - List of packages to display.
 * @returns {string} - HTML string for the table.
 */
export const PackagesTable = (packages) => {
    return `
        <div class="wp2-table-wrapper">
        <table class="wp2-table" data-wp2-table="packages">
            <thead>
                <tr>
                    <th>Package</th>
                    <th>Installed</th>
                    <th>Latest</th>
                    <th>Status</th>
                    <th>Stars</th>
                    <th>Issues</th>
                    <th style="text-align: right;">Action</th>
                </tr>
            </thead>
            <tbody>
                ${packages.map(pkg => {
                    const latestRelease = pkg.github_data?.latest_release || 'N/A';
                    const status = pkg.installed === latestRelease ? 'Up-to-date' : 'Outdated';

                    return `
                        <tr>
                            <td>${escapeHtml(pkg.name)}</td>
                            <td>${escapeHtml(pkg.installed)}</td>
                            <td>${escapeHtml(latestRelease)}</td>
                            <td>${escapeHtml(status)}</td>
                            <td>${escapeHtml(pkg.stars.toString())}</td>
                            <td>${escapeHtml(pkg.issues.toString())}</td>
                            <td style="text-align: right;">
                                <button class="wp2-btn wp2-btn-icon" data-wp2-action="package-details" data-wp2-package="${escapeHtml(pkg.id)}">
                                    <i class="icon icon-details"></i> Details
                                </button>
                                <button class="wp2-btn wp2-btn-icon wp2-btn-sync" data-wp2-action="sync-package" data-wp2-package="${escapeHtml(pkg.id)}">
                                    <i class="icon icon-sync"></i> Sync
                                </button>
                            </td>
                        </tr>
                    `;
                }).join('')}
            </tbody>
        </table>
        </div>
    `;
};
