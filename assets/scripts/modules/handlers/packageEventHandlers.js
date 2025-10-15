import { modalManager } from '../utils/modal.js';
import { PackageDetailsModal } from '../components/modals/PackageDetailsModal.js';
import { AssignAppModal } from '../components/modals/AssignAppModal.js';
import { RollbackModal } from '../components/modals/RollbackModal.js';
import { store } from '../state/store.js';
import { api_request } from '../api.js';

export function registerPackageHandlers(packageService) {
    document.addEventListener('click', (event) => {
        const target = event.target.closest('[data-action]');
        if (!target) return;

        const action = target.dataset.action;
        const repo = target.closest('[data-repo]')?.dataset.repo;

        if (!repo) return;

        const pkgRow = target.closest('tr');
        const pkg = store.get().packages.find(p => p.repo === repo);
        if (!pkg) return;

        switch (action) {
            case 'open-package-details':
                modalManager.open('packageDetailsModal', PackageDetailsModal(pkg));
                break;
            case 'open-assign-app':
                const onSubmitAssignApp = async (appId, repoSlug) => {
                    try {
                        await api_request('packages/assign', {
                            method: 'POST',
                            body: JSON.stringify({ app_id: appId, repo_slug: repoSlug })
                        }, 'wp2_assign_package');

                        NotificationService.showSuccess(__('App assigned successfully!', 'wp2-update')); // Ensure proper toast integration

                        // Update the state to reflect the assignment
                        store.update(state => {
                            const updatedPackages = state.packages.map(p =>
                                p.repo === repo ? { ...p, appId } : p
                            );
                            return { ...state, packages: updatedPackages };
                        });
                    } catch (error) {
                        console.error(`Failed to assign app to ${repo}:`, error);
                        NotificationService.showError(__('Failed to assign app. Please try again.', 'wp2-update')); // Ensure proper toast integration
                    }
                };

                modalManager.open('assignAppModal', AssignAppModal(pkg, onSubmitAssignApp));
                break;
            case 'open-rollback':
                const onSubmitRollback = async (selectedVersion) => {
                    try {
                        await api_request('packages/action', {
                            method: 'POST',
                            body: JSON.stringify({
                                repo_slug: repo,
                                action: 'rollback',
                                version: selectedVersion
                            })
                        }, 'wp2_package_action');

                        NotificationService.showSuccess(__('Rollback successful!', 'wp2-update')); // Ensure proper toast integration

                        // Update the state to reflect the rollback
                        store.update(state => {
                            const updatedPackages = state.packages.map(p =>
                                p.repo === repo ? { ...p, version: selectedVersion } : p
                            );
                            return { ...state, packages: updatedPackages };
                        });
                    } catch (error) {
                        console.error(`Failed to rollback ${repo}:`, error);
                        NotificationService.showError(__('Failed to rollback. Please try again.', 'wp2-update'));
                    }
                };

                modalManager.open('rollbackModal', RollbackModal(pkg, onSubmitRollback));
                break;
            case 'toggle-auto-update':
                const isEnabled = !pkg.autoUpdate;

                // Optimistically update the state
                store.update(state => {
                    const updatedPackages = state.packages.map(p =>
                        p.repo === repo ? { ...p, autoUpdate: isEnabled } : p
                    );
                    return { ...state, packages: updatedPackages };
                });

                // Perform the API call in the background
                packageService.toggleAutoUpdate(repo, isEnabled).catch(error => {
                    console.error(`Failed to toggle auto-update for ${repo}:`, error);

                    // Revert the state on failure
                    store.update(state => {
                        const revertedPackages = state.packages.map(p =>
                            p.repo === repo ? { ...p, autoUpdate: !isEnabled } : p
                        );
                        return { ...state, packages: revertedPackages };
                    });
                });
                break;
            case 'update-package':
                if (pkgRow) {
                    const updateButton = pkgRow.querySelector('[data-action="update-package"]');
                    updateButton.disabled = true;
                    updateButton.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Updating';
                    pkgRow.classList.add('updating-row');
                }

                packageService.updatePackage(repo)
                    .then((updatedPackage) => {
                        if (pkgRow) {
                            pkgRow.classList.remove('updating-row');
                            pkgRow.querySelector('[data-column="version"]').textContent = updatedPackage.version;
                            pkgRow.querySelector('[data-column="status"]').innerHTML = '<span class="badge bg-success">Up to date</span>';
                            const actionsCell = pkgRow.querySelector('[data-column="actions"]');
                            actionsCell.innerHTML = '<button class="btn btn-secondary btn-sm" disabled>Updated</button>';
                        }
                        NotificationService.showSuccess(`Package ${updatedPackage.name} updated successfully.`);
                    })
                    .catch((error) => {
                        console.error(`Failed to update package ${repo}:`, error);
                        if (pkgRow) {
                            pkgRow.classList.remove('updating-row');
                            const updateButton = pkgRow.querySelector('[data-action="update-package"]');
                            updateButton.disabled = false;
                            updateButton.textContent = 'Update';
                        }
                        NotificationService.showError(`Failed to update package ${pkg.name}.`);
                    });
                break;
        }
    });
}
