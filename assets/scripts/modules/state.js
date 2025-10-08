/**
 * @file src-js/modules/state.js
 * @description Centralized state management using nanostores.
 */

import { atom, computed } from 'nanostores';

/**
 * @typedef {'pre-connection' | 'credentials' | 'syncing' | 'managing'} AppStage
 * @typedef {'pending' | 'success' | 'error'} ValidationStatus
 * @typedef {'idle' | 'updating' | 'rollback'} PackageStatus
 */

/**
 * @typedef {object} ValidationStep
 * @property {string} text
 * @property {ValidationStatus} status
 * @property {string} detail
 */

/**
 * @typedef {object} Package
 * @property {string} name
 * @property {string} repo
 * @property {boolean} installed
 * @property {any} releases
 * @property {any} selectedVersion
 * @property {PackageStatus} status
 */

/**
 * Manages the global application state.
 * @type {import('nanostores').WritableAtom<{
 * currentStage: AppStage;
 * isLoading: boolean;
 * connection: {
 * health: {
 * lastSync: string;
 * apiRemaining: string;
 * webhookOk: boolean;
 * };
 * validation: {
 * steps: ValidationStep[];
 * };
 * };
 * packages: Package[];
 * }>}
 */
export const appState = atom({
    currentStage: 'pre-connection',
    isLoading: false,
    connection: {
        health: {
            lastSync: 'N/A',
            apiRemaining: 'N/A',
            webhookOk: false,
        },
        validation: {
            steps: [],
        },
    },
    packages: [],
});

/**
 * Derived state to check if any package is currently updating.
 * @type {import('nanostores').ReadableAtom<boolean>}
 */
export const isAnyPackageUpdating = computed(appState, (state) => {
    return state.packages.some((pkg) => pkg.status === 'updating');
});

// Utility function to persist state to sessionStorage
const persistState = (key, value) => {
    try {
        sessionStorage.setItem(key, JSON.stringify(value));
    } catch (error) {
        console.error('Failed to persist state:', error);
    }
};

// Utility function to restore state from sessionStorage
const restoreState = (key, defaultValue) => {
    try {
        const storedValue = sessionStorage.getItem(key);
        return storedValue ? JSON.parse(storedValue) : defaultValue;
    } catch (error) {
        console.error('Failed to restore state:', error);
        return defaultValue;
    }
};

// Utility function to validate the structure of restored state
const validateRestoredState = (state, defaultValue) => {
    const isValid = (state) => {
        return (
            typeof state === 'object' &&
            typeof state.currentStage === 'string' &&
            typeof state.isLoading === 'boolean' &&
            typeof state.connection === 'object' &&
            Array.isArray(state.packages)
        );
    };

    if (!isValid(state)) {
        console.warn('Restored state is invalid. Using default state.');
        return defaultValue;
    }

    return state;
};

// Restore specific state properties on initialization
const restoredState = restoreState('appState', {
    currentStage: 'pre-connection',
    isLoading: false,
    connection: {
        health: {
            lastSync: 'N/A',
            apiRemaining: 'N/A',
            webhookOk: false,
        },
        validation: {
            steps: [],
        },
    },
    packages: [],
});

// Validate the restored state
const validRestoredState = validateRestoredState(restoredState, {
    currentStage: 'pre-connection',
    isLoading: false,
    connection: {
        health: {
            lastSync: 'N/A',
            apiRemaining: 'N/A',
            webhookOk: false,
        },
        validation: {
            steps: [],
        },
    },
    packages: [],
});

// Initialize appState with restored values
appState.set(validRestoredState);

// Subscribe to appState changes and persist specific properties
appState.subscribe((state) => {
    persistState('appState', {
        currentStage: state.currentStage,
        connection: state.connection,
        packages: state.packages,
    });
});