import { unified_state } from '../../state/store';

// Modal for assigning an app

export const AssignAppModal = (app, packages) => {
    let packageOptions = packages.map(pkg => `<option value="${pkg.id}">${pkg.name}</option>`).join('');

    const render = () => {
        const modalContent = document.querySelector('.wp2-modal-content');
        if (modalContent) {
            modalContent.innerHTML = `
                <div class="wp2-modal-header">
                    <h2>Assign App</h2>
                </div>
                <div class="wp2-modal-body">
                    <form id="wp2-assign-app-form">
                        <label for="wp2-package-select">Select Package</label>
                        <select id="wp2-package-select" name="packageId">
                            ${packageOptions}
                        </select>
                    </form>
                </div>
                <div class="wp2-modal-footer">
                    <button type="submit" class="wp2-btn wp2-btn-primary">Assign</button>
                </div>
            `;
        }
    };

    // Subscribe to unified state updates
    unified_state.subscribe(() => {
        const state = unified_state.get();
        if (state.packages) {
            packages = state.packages;
            packageOptions = packages.map(pkg => `<option value="${pkg.id}">${pkg.name}</option>`).join('');
            render();
        }

        const selectedAppId = state.selectedAppId;
        const modalContent = document.querySelector('.wp2-modal-content');
        if (modalContent) {
            modalContent.querySelector('h2').textContent = `Assign App (Selected App ID: ${selectedAppId})`;
        }
    });

    return `
        <div class="wp2-modal-content">
            <div class="wp2-modal-header">
                <h2>Assign App</h2>
            </div>
            <div class="wp2-modal-body">
                <form id="wp2-assign-app-form">
                    <label for="wp2-package-select">Select Package</label>
                    <select id="wp2-package-select" name="packageId">
                        ${packageOptions}
                    </select>
                </form>
            </div>
            <div class="wp2-modal-footer">
                <button type="submit" class="wp2-btn wp2-btn-primary">Assign</button>
            </div>
        </div>
    `;
};