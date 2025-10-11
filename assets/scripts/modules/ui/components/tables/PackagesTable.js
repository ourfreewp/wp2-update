// Table for displaying packages

/**
 * Render a table for displaying packages.
 * @param {Array} packages - List of packages to display.
 * @returns {string} - HTML string for the table.
 */
export const PackagesTable = (packages) => {
    return `
        <table>
            <thead>
                <tr>
                    <th>Package</th>
                    <th>Installed</th>
                    <th>Latest</th>
                    <th>Status</th>
                    <th>Managed By</th>
                    <th style="text-align: right;">Action</th>
                </tr>
            </thead>
            <tbody>
                ${packages.map(pkg => `
                    <tr>
                        <td>${pkg.name}</td>
                        <td>${pkg.installed}</td>
                        <td>${pkg.latest}</td>
                        <td>${pkg.status}</td>
                        <td>${pkg.managedBy}</td>
                        <td style="text-align: right;">
                            <!-- Actions should be handled externally -->
                        </td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;
};