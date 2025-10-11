/**
 * Renders the LoadingView component.
 * Displays a loading spinner or message.
 * @returns {string} - HTML string for the LoadingView.
 */
export const loadingView = () => {
    return `
        <div class="wp2-loading-view">
            <div class="wp2-spinner"></div>
            <p>Loading, please wait...</p>
        </div>
    `;
};