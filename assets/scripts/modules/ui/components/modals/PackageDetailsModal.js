import { unified_state } from '../../state/store';

// Modal for displaying package details
export const PackageDetailsModal = (pkg) => {
    return `
        <div class="wp2-modal-content">
            <div class="wp2-modal-header">
                <h2>${pkg.name}</h2>
            </div>
            <div class="wp2-modal-body">
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
            <div class="wp2-modal-footer">
                <button class="wp2-btn wp2-btn-secondary">Close</button>
            </div>
        </div>
    `;
};

// Subscribe to state updates
unified_state.subscribe(() => {
    const state = unified_state.get();
    const selectedPackage = state.packages.find(pkg => pkg.id === state.details?.selectedPackageId);

    const modalContent = document.querySelector('.wp2-modal-content');
    if (modalContent && selectedPackage) {
        modalContent.innerHTML = `
            <div class="wp2-modal-header">
                <h2>${selectedPackage.name}</h2>
            </div>
            <div class="wp2-modal-body">
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
            </div>
            <div class="wp2-modal-footer">
                <button class="wp2-btn wp2-btn-secondary">Close</button>
            </div>
        `;
    }
});
