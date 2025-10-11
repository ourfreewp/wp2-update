import { dashboard_state } from '../../state/store';

// Modal for displaying package details
export const PackageDetailsModal = (pkg) => {
    return `
        <div class="wp2-modal-content">
            <h2>${pkg.name}</h2>
            <dl class="wp2-detail-grid">
                <dt>Repository</dt>
                <dd>${pkg.repo}</dd>
                <dt>Installed Version</dt>
                <dd>${pkg.installed}</dd>
                <dt>Latest Version</dt>
                <dd>${pkg.latest}</dd>
                <dt>Status</dt>
                <dd>${pkg.status}</dd>
            </dl>
        </div>
    `;
};

// Subscribe to dashboard state updates
dashboard_state.subscribe(() => {
    const state = dashboard_state.get();
    const selectedPackage = state.packages.find(pkg => pkg.id === state.details?.selectedPackageId);

    const modalContent = document.querySelector('.wp2-modal-content');
    if (modalContent && selectedPackage) {
        modalContent.innerHTML = `
            <h2>${selectedPackage.name}</h2>
            <dl class="wp2-detail-grid">
                <dt>Repository</dt>
                <dd>${selectedPackage.repo}</dd>
                <dt>Installed Version</dt>
                <dd>${selectedPackage.installed}</dd>
                <dt>Latest Version</dt>
                <dd>${selectedPackage.latest}</dd>
                <dt>Status</dt>
                <dd>${selectedPackage.status}</dd>
            </dl>
        `;
    }
});