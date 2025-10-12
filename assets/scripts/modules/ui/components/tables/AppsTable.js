import { escapeHtml } from '../../../utils.js';

/**
 * Render a table for displaying apps.
 * @param {Array} apps - List of apps to display.
 * @returns {string} - HTML string for the table.
 */
export const AppsTable = (apps) => {
    return `
        <div class="wp2-table-wrapper">
        <table class="wp2-table" data-wp2-table="apps">
            <thead>
                <tr>
                    <th>App Name</th>
                    <th>Account Type</th>
                    <th>Packages</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                ${apps.map(app => `
                    <tr>
                        <td>${escapeHtml(app.name)}</td>
                        <td>${escapeHtml(app.account_type)}</td>
                        <td>${escapeHtml(app.packageCount)}</td>
                        <td style="text-align: right;">
                            <button class="wp2-btn wp2-btn-icon" data-wp2-action="app-details" data-wp2-app="${escapeHtml(app.id)}">
                                <i class="icon icon-details"></i> Details
                            </button>
                            <button class="wp2-btn wp2-btn-icon wp2-btn-danger" data-wp2-action="delete-app" data-wp2-app="${escapeHtml(app.id)}">
                                <i class="icon icon-delete"></i> Delete
                            </button>
                        </td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
        </div>
    `;
};
