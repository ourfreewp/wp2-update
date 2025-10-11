import { dashboard_state } from '../../state/store';

/**
 * Renders the WaitingView component.
 * Displays the installation URL and a checklist of repositories.
 * Subscribes to state updates for dynamic re-rendering.
 * @returns {string} - HTML string for the WaitingView.
 */
export const WaitingView = () => {
    const render = () => {
        const state = dashboard_state.get();
        const { installUrl, managedRepositories = [] } = state.details || {};

        const repoChecklist = managedRepositories.map(repo => `
            <li>
                <label>
                    <input type="checkbox" disabled ${repo.installed ? 'checked' : ''} />
                    ${repo.name}
                </label>
            </li>
        `).join('');

        document.querySelector('.wp2-waiting-view').innerHTML = `
            <h2>Complete Installation</h2>
            <p>Please complete the installation process by visiting the following URL:</p>
            <a href="${installUrl}" target="_blank" rel="noopener noreferrer" class="wp2-btn wp2-btn-primary">
                Complete Installation
            </a>

            <h3>Repository Checklist</h3>
            <ul class="wp2-repo-checklist">
                ${repoChecklist}
            </ul>
        `;
    };

    dashboard_state.subscribe(render);

    // Initial render
    return `
        <div class="wp2-waiting-view">
            <!-- Content will be dynamically updated -->
        </div>
    `;
};