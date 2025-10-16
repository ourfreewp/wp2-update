import { modalManager } from '../utils/modal.js';
import { PackageDetailsModal } from '../components/modals/PackageDetailsModal.js';
import { AssignAppModal } from '../components/modals/AssignAppModal.js';
import { RollbackModal } from '../components/modals/RollbackModal.js';
import { store } from '../state/store.js';
import { apiFetch } from '@wordpress/api-fetch';

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
                        await apiFetch({
                            path: '/wp2-update/v1/packages/assign',
                            method: 'POST',
                            data: { app_id: appId, repo_slug: repoSlug },
                        });

                        NotificationService.showSuccess(__('App assigned successfully!', 'wp2-update'));

                        store.update(state => {
                            const updatedPackages = state.packages.map(p =>
                                p.repo === repo ? { ...p, appId } : p
                            );
                            return { ...state, packages: updatedPackages };
                        });
                    } catch (error) {
                        NotificationService.showError(__('Failed to assign app.', 'wp2-update'));
                    }
                };

                modalManager.open('assignAppModal', AssignAppModal(pkg, onSubmitAssignApp));
                break;
            case 'rollback-package':
                modalManager.open('rollbackModal', RollbackModal(pkg));
                break;
            default:
                console.warn(`Unhandled action: ${action}`);
        }
    });
}
