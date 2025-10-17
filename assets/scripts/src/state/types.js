/**
 * @typedef {Object} Package
 * @property {string} id - The unique identifier for the package.
 * @property {string} name - The display name of the package.
 * @property {string} version - The current version string.
 * @property {string} repo - The repository slug (e.g., 'owner/repo').
 * @property {string} status - The current status of the package.
 */

/**
 * @typedef {Object} AppState
 * @property {string} status - The overall application status.
 * @property {boolean} isProcessing - True if a global process is running.
 * @property {Package[]} packages - The list of packages.
 * @property {'createPackage' | 'appDetails' | null} activeModal - The ID of the currently active modal.
 * @property {Object.<string, any>} modalProps - Properties passed to the active modal.
 * @property {Array<{id: number, type: 'success'|'danger', message: string}>} notifications - A list of active notifications.
 */