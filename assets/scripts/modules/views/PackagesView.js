import { PackageRow } from '../components/package/PackageRow.js';
import { store } from '../state/store.js';

const { __ } = wp.i18n;

export const initializePackagesView = () => {
    console.log('Packages interactions initialized.');

    store.subscribe(() => {
        const packages = store.get().packages;
        const packagesTableBody = document.querySelector('#packages-table tbody');
        if (packagesTableBody) {
            packagesTableBody.innerHTML = packages.map(PackageRow).join('');
        }
    });

    store.subscribe(() => {
        const { activeModal, releaseNotes } = store.get();
        const rollbackModalElement = document.getElementById('rollbackModal');
        const releaseNotesElement = document.getElementById('releaseNotes');

        if (rollbackModalElement && releaseNotesElement) {
            if (activeModal === 'rollbackModal') {
                releaseNotesElement.innerHTML = releaseNotes || '<p>Loading...</p>';
                const rollbackModal = new bootstrap.Modal(rollbackModalElement);
                rollbackModal.show();
            }
        }
    });

    document.addEventListener('click', (event) => {
        if (event.target && event.target.classList.contains('dropdown-item') && event.target.textContent.trim() === 'Rollback') {
            const packageRow = event.target.closest('tr');
            const packageSlug = packageRow?.dataset?.packageSlug;

            if (packageSlug) {
                updateState({ activeModal: 'rollbackModal', releaseNotes: '<p>Loading...</p>' });

                import('../services/PackageService.js').then(({ PackageService }) => {
                    const service = new PackageService();
                    service.getReleaseNotes(packageSlug).then((notes) => {
                        updateState({ releaseNotes: notes ? `<p>${notes}</p>` : '<p>No release notes available.</p>' });
                    }).catch(() => {
                        updateState({ releaseNotes: '<p>Failed to load release notes.</p>' });
                    });
                });
            }
        }
    });

    // Bulk action handlers
    const bulkActionSelector = document.getElementById('bulk-action-selector');
    const applyBulkActionButton = document.getElementById('apply-bulk-action');
    const selectAllCheckbox = document.getElementById('select-all-packages');

    // Select/Deselect all packages
    selectAllCheckbox?.addEventListener('change', (event) => {
        const checkboxes = document.querySelectorAll('.package-checkbox');
        checkboxes.forEach((checkbox) => {
            checkbox.checked = event.target.checked;
        });
    });

    // Apply bulk action
    applyBulkActionButton?.addEventListener('click', () => {
        const selectedAction = bulkActionSelector?.value;
        const selectedPackages = Array.from(document.querySelectorAll('.package-checkbox:checked')).map(
            (checkbox) => checkbox.value
        );

        if (!selectedAction || selectedPackages.length === 0) {
            alert(wp2UpdateData.i18n.selectAction);
            return;
        }

        switch (selectedAction) {
            case 'sync':
                console.log('Syncing packages:', selectedPackages);
                break;
            case 'update':
                console.log('Updating packages:', selectedPackages);
                break;
            case 'assign':
                console.log('Assigning app to packages:', selectedPackages);
                break;
            default:
                alert(wp2UpdateData.i18n.invalidAction);
        }
    });
};
