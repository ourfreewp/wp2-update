import { unified_state } from '../../state/store';

/**
 * Renders the WaitingView component.
 * Displays the installation URL and a checklist of repositories.
 * Subscribes to state updates for dynamic re-rendering.
 * @returns {string} - HTML string for the WaitingView.
 */
export const WaitingView = () => {
    const render = () => {
        const state = unified_state.get();
        const { installUrl, managedRepositories = [] } = state.details || {};

        const waitingViewContainer = document.querySelector('.wp2-waiting-view');
        if (!waitingViewContainer) return;

        // Clear existing content
        waitingViewContainer.innerHTML = '';

        // Create and append heading
        const heading = document.createElement('h2');
        heading.textContent = 'Complete Installation';
        waitingViewContainer.appendChild(heading);

        // Create and append installation instructions
        const instructions = document.createElement('p');
        instructions.textContent = 'Please complete the installation process by visiting the following URL:';
        waitingViewContainer.appendChild(instructions);

        // Create and append installation link
        const installLink = document.createElement('a');
        installLink.href = installUrl;
        installLink.target = '_blank';
        installLink.rel = 'noopener noreferrer';
        installLink.className = 'wp2-btn wp2-btn-primary';
        installLink.textContent = 'Complete Installation';
        waitingViewContainer.appendChild(installLink);

        // Create and append repository checklist heading
        const checklistHeading = document.createElement('h3');
        checklistHeading.textContent = 'Repository Checklist';
        waitingViewContainer.appendChild(checklistHeading);

        // Create and append repository checklist
        const checklist = document.createElement('ul');
        checklist.className = 'wp2-repo-checklist';

        managedRepositories.forEach(repo => {
            const listItem = document.createElement('li');

            const label = document.createElement('label');

            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.disabled = true;
            checkbox.checked = repo.installed;

            const repoName = document.createTextNode(` ${repo.name}`);

            label.appendChild(checkbox);
            label.appendChild(repoName);
            listItem.appendChild(label);
            checklist.appendChild(listItem);
        });

        waitingViewContainer.appendChild(checklist);
    };

    unified_state.subscribe(render);

    // Initial render
    return `
        <div class="wp2-waiting-view">
            <!-- Content will be dynamically updated -->
        </div>
    `;
};