import { syncAllPackages, createPackage } from '../services/PackageService';

/**
 * Handles the "Sync All" action.
 */
export function handleSyncAll() {
    try {
        syncAllPackages()
            .then(response => {
                console.log('Sync All completed successfully:', response);
            })
            .catch(error => {
                console.error('Error during Sync All:', error);
            });
    } catch (error) {
        console.error('Unexpected error during Sync All:', error);
    }
}

/**
 * Handles the "Create Package" action.
 */
export function handleCreatePackage(packageData) {
    try {
        createPackage(packageData)
            .then(response => {
                console.log('Package created successfully:', response);
            })
            .catch(error => {
                console.error('Error during package creation:', error);
            });
    } catch (error) {
        console.error('Unexpected error during package creation:', error);
    }
}