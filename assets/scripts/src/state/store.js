import { atom, computed } from 'nanostores';

// Define STATUS as an object with possible states
export const STATUS = {
    LOADING: 'loading',
    SUCCESS: 'success',
    ERROR: 'error',
    INSTALLED: 'installed',
    NOT_CONFIGURED: 'not_configured',
    NOT_CONFIGURED_WITH_PACKAGES: 'not_configured_with_packages',
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
    statusMessage: '', // For ARIA live region
    details: {},
    health: {},
    stats: {},
    packages: [],
    backups: [],
    selectedPackages: [],
    flags: {
        devMode: !!window.wp2UpdateData?.flags?.devMode,
        headless: !!window.wp2UpdateData?.flags?.headless,
    },
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

// Import modularized states
import { packages } from './packages';
import { apps } from './apps';

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

/**
 * Updates the state of a specific package.
 * @param {string} packageId - The ID of the package to update.
 * @param {Partial<Package>} newPackageState - The new state values for the package.
 */
export const updatePackageState = (packageId, newPackageState) => {
    updateState((state) => {
        const updatedPackages = state.packages.map((pkg) => {
            if (pkg.id === packageId) {
                return { ...pkg, ...newPackageState };
            }
            return pkg;
        });
        return { packages: updatedPackages };
    });
}

/**
 * Computed property to check if any package is updating.
 */
export const isAnyPackageUpdating = computed(store, ($store) =>
  $store.packages.some((pkg) => pkg.isUpdating)
);

/**
 * Computed property for overall loading state.
 */
export const isLoading = computed(store, ($store) =>
  $store.isProcessing || $store.packages.some((pkg) => pkg.isUpdating)
);
