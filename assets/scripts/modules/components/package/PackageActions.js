import { Dropdown } from '../ui/Dropdown.js';

/**
 * Renders the action buttons for a package row.
 * @param {object} pkg - The package data.
 * @returns {string} The HTML for the action buttons.
 */
export const PackageActions = (pkg) => {
    const dropdownItems = [];

    dropdownItems.push({
        id: 'open-package-details',
        label: __('Details', 'wp2-update'),
        icon: 'ℹ️',
    });

    if (pkg.has_update) {
        dropdownItems.push({
            id: 'update-package',
            label: __('Update', 'wp2-update'),
            icon: '🔄',
        });
    }

    if (pkg.is_installed) {
        dropdownItems.push({
            id: 'open-rollback',
            label: __('Rollback', 'wp2-update'),
            icon: '↩️',
        });
    } else {
        dropdownItems.push({
            id: 'install-package',
            label: __('Install', 'wp2-update'),
            icon: '⬇️',
        });
    }

    if (!pkg.is_managed) {
        dropdownItems.push({
            id: 'open-assign-app',
            label: __('Assign App', 'wp2-update'),
            icon: '🔗',
        });
    }

    return Dropdown(dropdownItems);
};
