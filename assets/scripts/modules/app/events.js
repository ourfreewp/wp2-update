import debounce from 'lodash/debounce';
import { actions } from './actions.js';
import { app_state } from '../state/store.js';

export const attachEventListeners = () => {
    document.addEventListener('click', (e) => {
        const button = e.target.closest('button[data-wp2-action]');
        const actionName = button?.dataset.wp2Action;
        if (actionName && actions[actionName]) {
            e.preventDefault();
            actions[actionName](button);
        }
    });

    document.addEventListener('submit', (e) => {
        if (e.target.id === 'wp2-manifest-config-form') {
            e.preventDefault();
            const button = e.target.querySelector('[data-wp2-action="submit-manifest-config"]');
            if (button) {
                const actionName = button.dataset.wp2Action;
                if (actionName && actions[actionName]) {
                    actions[actionName](button);
                }
            }
        }
    });

    const handleFormUpdate = (target) => {
        const { name, value } = target;
        const state = app_state.get();
        const draft = { ...state.manifestDraft };

        if (name === 'app-name') draft.name = value;
        if (name === 'app-type') draft.accountType = value;
        if (name === 'organization') draft.organization = value;

        app_state.set({ ...state, manifestDraft: draft });
    };

    // Use 'input' for text fields for a responsive feel while typing
    document.addEventListener('input', debounce((e) => {
        if (e.target.closest('#wp2-manifest-config-form') && e.target.matches('input[type="text"]')) {
            handleFormUpdate(e.target);
        }
    }, 300));

    // Use 'change' for the select dropdown to capture its value when changed
    document.addEventListener('change', (e) => {
        if (e.target.closest('#wp2-manifest-config-form') && e.target.matches('select[name="app-type"]')) {
            handleFormUpdate(e.target);
        }
    });

    window.addEventListener('message', (event) => {
        if (event.data === 'wp2-update-github-connected') {
            actions.syncPackages();
        }
    });
};