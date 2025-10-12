// Handles event listeners for app-related actions

import { unified_state, updateUnifiedState } from '../../state/store.js';

const onFormFieldInput = (event) => {
    const { id, value } = event.target;
    const currentState = unified_state.get();
    const draft = { ...currentState.manifestDraft };

    switch (id) {
        case 'wp2-encryption-key':
            draft.encryptionKey = value;
            break;
        case 'wp2-app-name':
            draft.name = value;
            break;
        case 'wp2-organization':
            draft.organization = value;
            break;
        case 'wp2-manifest-json':
            draft.manifestJson = value;
            break;
        default:
            return;
    }

    updateUnifiedState({ manifestDraft: draft });
};

const toggleOrgFields = (accountType) => {
    document.querySelectorAll('.wp2-org-field').forEach((field) => {
        field.hidden = accountType !== 'organization';
    });
};

export { onFormFieldInput, toggleOrgFields };