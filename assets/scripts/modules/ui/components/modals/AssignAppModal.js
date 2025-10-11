import { dashboard_state, app_state } from '../../state/store';

// Modal for assigning an app

export const AssignAppModal = (app, packages) => {
    let packageOptions = packages.map(pkg => `<option value="${pkg.id}">${pkg.name}</option>`).join('');

    const render = () => {
        const modalContent = document.querySelector('.wp2-modal-content');
        if (modalContent) {
            modalContent.innerHTML = `
                <h2>Assign App</h2>
                <form id="wp2-assign-app-form">
                    <label for="wp2-package-select">Select Package</label>
                    <select id="wp2-package-select" name="packageId">
                        ${packageOptions}
                    </select>
                    <button type="submit" class="wp2-btn wp2-btn-primary">Assign</button>
                </form>
            `;
        }
    };

    // Subscribe to store updates
    dashboard_state.subscribe(() => {
        const state = dashboard_state.get();
        if (state.packages) {
            packages = state.packages;
            packageOptions = packages.map(pkg => `<option value="${pkg.id}">${pkg.name}</option>`).join('');
            render();
        }
    });

    // Subscribe to app state updates
    app_state.subscribe(() => {
        const state = app_state.get();
        const selectedAppId = state.selectedAppId;

        const modalContent = document.querySelector('.wp2-modal-content');
        if (modalContent) {
            modalContent.querySelector('h2').textContent = `Assign App (Selected App ID: ${selectedAppId})`;
        }
    });

    return `
        <div class="wp2-modal-content">
            <h2>Assign App</h2>
            <form id="wp2-assign-app-form">
                <label for="wp2-package-select">Select Package</label>
                <select id="wp2-package-select" name="packageId">
                    ${packageOptions}
                </select>
                <button type="submit" class="wp2-btn wp2-btn-primary">Assign</button>
            </form>
        </div>
    `;
};