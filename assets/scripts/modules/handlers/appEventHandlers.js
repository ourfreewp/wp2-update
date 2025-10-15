import { modalManager } from '../utils/modal.js';
import { AppDetailsModal } from '../components/modals/AppDetailsModal.js';
import { store } from '../state/store.js';

export function registerAppHandlers(appService, connectionService) {
    document.addEventListener('click', (event) => {
        const target = event.target.closest('[data-action]');
        if (!target) return;

        const action = target.dataset.action;
        const appId = target.closest('[data-app-id]')?.dataset.appId;

        if (action === 'open-wizard') {
            // Removed redundant call to initializeWizardModal
        }

        if (action === 'open-app-details' && appId) {
            const app = store.get().apps.find(a => a.id === appId);
            if (app) {
                modalManager.open('appDetailsModal', AppDetailsModal(app));
            }
        }

        if (action === 'magic-app-create') {
            const button = target;
            button.disabled = true;
            button.textContent = 'Creating...';

            const appId = target.dataset.appId;
            const pollInterval = 3000; // 3 seconds

            const pollAppStatus = async () => {
                try {
                    const response = await appService.getAppStatus(appId);
                    if (response.status === 'installed') {
                        button.textContent = 'App Installed';
                        button.disabled = false;
                        clearInterval(polling);
                    } else if (response.status === 'failed') {
                        button.textContent = 'Installation Failed';
                        button.disabled = false;
                        clearInterval(polling);
                    }
                } catch (error) {
                    console.error('Error polling app status:', error);
                }
            };

            const polling = setInterval(pollAppStatus, pollInterval);
        }
    });
}
