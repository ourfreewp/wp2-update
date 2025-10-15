import { PackageRow } from '../components/package/PackageRow.js';
import { store } from '../state/store.js';

const { __ } = wp.i18n;

// This file is now focused solely on interactions for the Packages tab.

export const initializePackagesView = () => {
    console.log('Packages interactions initialized.');

    const packagesTableBody = document.querySelector('#packages-table tbody');

    // Function to render packages dynamically
    const renderPackages = (packages) => {
        packagesTableBody.innerHTML = packages.map(PackageRow).join('');
    };

    // Listen for state updates
    // Decoupled rendering from state subscription
    store.subscribe(() => {
        const packages = store.get().packages;
        renderPackages(packages);
    });

    document.addEventListener('click', (event) => {
        if (event.target && event.target.dataset.wp2Action === 'view-package-details') {
            const packageSlug = event.target.closest('tr').dataset.packageSlug;
            console.log(`View package details interaction triggered for: ${packageSlug}`);

            // Trigger a custom event for viewing package details
            const viewPackageEvent = new CustomEvent('wp2ViewPackageDetails', {
                detail: { packageSlug },
            });
            document.dispatchEvent(viewPackageEvent);
        }
    });
};
