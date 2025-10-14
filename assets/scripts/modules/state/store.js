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

// Add granular in-progress states for packages
store.set({
    ...store.get(),
    packages: store.get().packages.map((pkg) => ({
        ...pkg,
        isUpdating: false, // Default state for tracking updates
        isRollingBack: false, // Default state for tracking rollbacks
    })),
});

/**
 * Updates the in-progress state for a specific package.
 * @param {string} packageId - The ID of the package to update.
 * @param {string} action - The action being performed (e.g., 'update', 'rollback').
 * @param {boolean} inProgress - Whether the action is in progress.
 */
export const setPackageProgress = (packageId, action, inProgress) => {
    updateState((state) => {
        const updatedPackages = state.packages.map((pkg) => {
            if (pkg.id === packageId) {
                return {
                    ...pkg,
                    isUpdating: action === 'update' ? inProgress : pkg.isUpdating,
                    isRollingBack: action === 'rollback' ? inProgress : pkg.isRollingBack,
                };
            }
            return pkg;
        });
        return { packages: updatedPackages };
    });
};
