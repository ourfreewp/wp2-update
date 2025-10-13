import { modalManager } from '../utils/modal.js';
import { WizardModal } from '../components/modals/WizardModal.js';
import { AppDetailsModal } from '../components/modals/AppDetailsModal.js';
import { store } from '../state/store.js';

export function registerAppHandlers(appService, connectionService) {
    document.addEventListener('click', (event) => {
        const target = event.target.closest('[data-action]');
        if (!target) return;

        const action = target.dataset.action;
        const appId = target.closest('[data-app-id]')?.dataset.appId;

        if (action === 'open-wizard') {
            modalManager.open(WizardModal());
        }

        if (action === 'open-app-details' && appId) {
            const app = store.get().apps.find(a => a.id === appId);
            if (app) {
                modalManager.open(AppDetailsModal(app));
            }
        }
    });
}
