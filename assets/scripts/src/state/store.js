import { atom } from 'nanostores';

// Define STATUS as an object with possible states
export const STATUS = {
    LOADING: 'loading',
    SUCCESS: 'success',
    ERROR: 'error',
    INSTALLED: 'installed',
};

/**
 * @typedef {import('./types.js').AppState} AppState */
/** @typedef {import('./types.js').Package} Package */

/**
 * @typedef {Object} ModalState
 * @property {boolean} isOpen - Whether the modal is open.
 * @property {any} content - The content of the modal.
 */

/**
 * @type {import('nanostores').Atom<AppState>}
 */
export const store = atom({
    status: STATUS.LOADING,
    isProcessing: false,
    message: '',
    details: {},
    apps: [],
    selectedAppId: null,
    packages: [],
    health: {},
    stats: {},
    modal: {
        isOpen: false,
        content: null,
    },
    form: {},
    activeModal: null, // e.g., 'createPackage', 'appDetails'
    modalProps: {},    // Data to pass to the modal component
    notifications: [], // Initialize notifications as an empty array
});

/**
 * Updates the global state.
 * @param {Partial<AppState>} newState - An object with new state values.
 */
export const updateState = (newState) => {
    store.set({ ...store.get(), ...newState });
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

// Added granular loading states for better UI responsiveness

/**
 * Updates the loading state for a specific package.
 * @param {string} packageId - The ID of the package to update.
 * @param {boolean} isLoading - Whether the package is currently loading.
 */
export const setPackageLoadingState = (packageId, isLoading) => {
    updateState((state) => {
        const updatedPackages = state.packages.map((pkg) => {
            if (pkg.id === packageId) {
                return {
                    ...pkg,
                    isLoading,
                };
            }
            return pkg;
        });
        return { packages: updatedPackages };
    });
};
