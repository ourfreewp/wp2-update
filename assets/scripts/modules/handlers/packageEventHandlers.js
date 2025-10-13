import { modalManager } from '../utils/modal.js';
import { PackageDetailsModal } from '../components/modals/PackageDetailsModal.js';
import { AssignAppModal } from '../components/modals/AssignAppModal.js';
import { RollbackModal } from '../components/modals/RollbackModal.js';
import { store } from '../state/store.js';

export function registerPackageHandlers(packageService) {
    document.addEventListener('click', (event) => {
        const target = event.target.closest('[data-action]');
        if (!target) return;

        const action = target.dataset.action;
        const repo = target.closest('[data-repo]')?.dataset.repo;

        if (!repo) return;

        const pkg = store.get().packages.find(p => p.repo === repo);
        if (!pkg) return;

        switch (action) {
            case 'open-package-details':
                modalManager.open(PackageDetailsModal(pkg));
                break;
            case 'open-assign-app':
                modalManager.open(AssignAppModal(pkg));
                break;
            case 'open-rollback':
                modalManager.open(RollbackModal(pkg));
                break;
        }
    });
}
