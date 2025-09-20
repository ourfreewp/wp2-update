// ========================================================================
// File: src-js/modules/state.js
// Description: Manages the global state of the application using Nano Stores.
// ========================================================================

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
