import { Dropdown } from './Dropdown.js';

/**
 * Renders the action buttons for a package row.
 * @param {object} pkg - The package data.
 * @returns {string} The HTML for the action buttons.
 */
export const PackageActions = (pkg) => {
    const dropdownItems = [];

    dropdownItems.push({
        id: 'open-package-details',
        label: 'Details',
        icon: 'ℹ️',
    });

    if (pkg.has_update) {
        dropdownItems.push({
            id: 'update-package',
            label: 'Update',
            icon: '🔄',
        });
    }

    if (pkg.is_installed) {
        dropdownItems.push({
            id: 'open-rollback',
            label: 'Rollback',
            icon: '↩️',
        });
    } else {
        dropdownItems.push({
            id: 'install-package',
            label: 'Install',
            icon: '⬇️',
        });
    }

    if (!pkg.is_managed) {
        dropdownItems.push({
            id: 'open-assign-app',
            label: 'Assign App',
            icon: '🔗',
        });
    }

    return Dropdown(dropdownItems);
};
