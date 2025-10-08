/**
 * @file src-js/modules/components.js
 * @description Contains functions that generate and render the application's HTML UI.
 */

/**
 * Renders the main view based on the current workflow stage.
 * @param {string} currentStage - The current stage from the app state.
 */
export const renderAppView = (currentStage) => {
	document.querySelectorAll('.workflow-step').forEach((el) => {
		el.hidden = el.id !== currentStage;
	});
};

// Localization object for strings
const localization = {
    noReleasesFound: 'No releases found.',
    notInstalled: 'Not Installed',
    updating: 'Updating...',
    installUpdate: 'Install / Update',
    loadingPackages: 'Loading packages, please wait...',
    noRepositories: 'No repositories found. Please ensure your GitHub App has been granted access to the correct repositories on GitHub.com.'
};

/**
 * Creates the HTML for a single package row in the management table.
 * @param {object} pkg - The package data object from the state.
 * @returns {string} - The HTML string for a table row.
 */
const createPackageRow = (pkg) => {
    const isUpdating = pkg.status === 'updating';
    const releases = pkg.releases || [];
    const hasReleases = releases.length > 0;

    return `
        <tr id="package-row-${pkg.repo.replace('/', '-')}" class="${isUpdating ? 'updating' : ''}">
            <td class="package-name">
                <strong>${pkg.name}</strong><br>
                <a href="https://github.com/${pkg.repo}" target="_blank" rel="noopener noreferrer">${pkg.repo}</a>
            </td>
            <td class="package-version">
                ${pkg.installed ? `v${pkg.installed}` : `<span class="description">${localization.notInstalled}</span>`}
            </td>
            <td class="package-releases">
                ${!hasReleases
                    ? `<span class="description">${localization.noReleasesFound}</span>`
                    : `<select class="release-dropdown" data-package-repo="${pkg.repo}" ${isUpdating ? 'disabled' : ''}>
                          ${releases.map(rel => `<option value="${rel.tag_name}">${rel.name} ${rel.prerelease ? '(Pre-release)' : ''}</option>`).join('')}
                       </select>`
                }
            </td>
            <td class="package-actions">
                <button
                    class="button button-primary"
                    data-action="update-package"
                    data-package-repo="${pkg.repo}"
                    ${!hasReleases || isUpdating ? 'disabled' : ''}>
                    ${isUpdating ? `<span class="spinner is-active"></span> ${localization.updating}` : localization.installUpdate}
                </button>
            </td>
        </tr>
    `;
};

/**
 * Renders the entire table of packages, including loading (skeleton) states.
 * @param {Array} packages - The list of packages from the app state.
 * @param {boolean} isLoading - The global loading state.
 */
export const renderPackageTable = (packages, isLoading) => {
    const tbody = document.querySelector('#wp2-package-table tbody');
    if (!tbody) return;

    if (isLoading) {
        tbody.innerHTML = `
            <tr><td colspan="4" class="loading-message">${localization.loadingPackages}</td></tr>
        `;
        return;
    }

    if (!packages || packages.length === 0) {
        tbody.innerHTML = `
            <tr><td colspan="4" class="empty-message">
                ${localization.noRepositories}
            </td></tr>
        `;
        return;
    }

    tbody.innerHTML = packages.map(createPackageRow).join('');
};