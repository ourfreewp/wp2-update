// Table for displaying apps

/**
 * Render a table for displaying apps.
 * @param {Array} apps - List of apps to display.
 * @returns {string} - HTML string for the table.
 */
export const AppsTable = (apps) => {
    return `
        <table>
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
                        <td>${app.name}</td>
                        <td>${app.type}</td>
                        <td>${app.packageCount}</td>
                        <td style="text-align: right;">
                            <!-- Actions should be handled externally -->
                        </td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;
};