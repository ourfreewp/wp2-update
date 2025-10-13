import { atom } from 'nanostores';

export const STATUS = {
    LOADING: 'loading',
    NOT_CONFIGURED: 'not_configured',
    NOT_CONFIGURED_WITH_PACKAGES: 'not_configured_with_packages',
    CONFIGURING: 'configuring',
    MANUAL_CONFIGURING: 'manual_configuring',
    APP_CREATED: 'app_created',
    CONNECTING: 'connecting',
    INSTALLED: 'installed',
    ERROR: 'error',
};

// The central state atom for the entire application
export const store = atom({
    status: STATUS.LOADING,
    isProcessing: false,
    message: '',
    details: {},
    apps: [],
    selectedAppId: null,
    packages: [], // Holds all packages (managed and unlinked)
    health: {},
    stats: {},
    modal: {
        isOpen: false,
        content: null,
    },
    form: {}, // Holds form input state
});

/**
 * Updates the global state.
 * @param {object | function} newState - An object with new state values or a function that receives the current state and returns the new state.
 */
export const updateState = (newState) => {
    const currentState = store.get();
    const updates = typeof newState === 'function' ? newState(currentState) : newState;
    store.set({ ...currentState, ...updates });
};
