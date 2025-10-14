import { PackageRow } from '../components/package/PackageRow.js';
import { store } from '../state/store.js';

const { __ } = wp.i18n;

// This file is now focused solely on interactions for the Packages tab.

document.addEventListener('DOMContentLoaded', () => {
    console.log('Packages interactions initialized.');

    const packagesTableBody = document.querySelector('#packages-table tbody');

    // Function to render packages dynamically
    const renderPackages = () => {
        const packages = store.get().packages;
        packagesTableBody.innerHTML = packages.map(PackageRow).join('');
    };

    // Initial render
    renderPackages();

    // Listen for state updates
    store.subscribe(renderPackages);

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
});
