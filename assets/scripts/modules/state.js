import { atom } from 'nanostores';

/**
 * A central store for the GitHub connection status.
 * Components can subscribe to this store to reactively update the UI.
 * @type {import('nanostores').WritableAtom<{
 * connected: boolean | null;
 * message: string;
 * isLoading: boolean;
 * }>} */
export const connectionState = atom({
    connected: null, // null = initial, true = connected, false = disconnected/error
    message: 'Checking connection status...',
    isLoading: true,
});

/**
 * A central store for the application's entire state.
 * This manages the current workflow stage and all related data.
 * @type {import('nanostores').WritableAtom<{
 *   currentStage: string;
 *   isLoading: boolean;
 *   connection: {
 *     health: {
 *       lastSync: string;
 *       apiRemaining: string;
 *       webhookOk: boolean;
 *     };
 *     validation: {
 *       steps: Array<{ text: string; status: 'pending' | 'success' | 'error'; detail: string }>;
 *     };
 *   };
 *   packages: Array<{
 *     name: string;
 *     repo: string;
 *     installed: boolean;
 *     releases: any;
 *     selectedVersion: any;
 *     status: 'idle' | 'updating' | 'rollback';
 *   }>;
 * }>} */
export const appState = atom({
    // 'pre-connection', 'credentials', 'syncing', 'managing'
    currentStage: 'pre-connection', 
    isLoading: false,
    connection: {
        health: {
            lastSync: 'N/A',
            apiRemaining: 'N/A',
            webhookOk: false,
        },
        validation: {
            steps: [], // { text: '...', status: 'pending' | 'success' | 'error', detail: '' }
        },
    },
    packages: [], // { name, repo, installed, releases, selectedVersion, status: 'idle' | 'updating' | 'rollback' }
});
