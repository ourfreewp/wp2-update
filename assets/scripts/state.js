import { atom } from 'nanostores';

// Define the global application state
export const appState = atom({
  packages: [],
  isLoading: false,
  connection: {
    health: {
      lastSync: null,
    },
  },
  error: null, // Add a global error state
});