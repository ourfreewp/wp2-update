import { AppService } from '../../services/AppService';

// Wizard for adding a new app

export const AddAppWizard = () => {
    // Handle requires_installation flag
    const handleRequiresInstallation = (app) => {
        if (app.requires_installation) {
            alert(`App "${app.name}" requires installation. Please follow the instructions provided.`);
        }
    };

    const handleFormSubmit = async (event) => {
        event.preventDefault();

        const form = event.target;
        const appName = form.querySelector('#wp2-app-name').value;
        const appType = form.querySelector('#wp2-app-type').value;

        const submitButton = form.querySelector('button[type="submit"]');
        const originalText = submitButton.textContent;
        submitButton.disabled = true;
        submitButton.textContent = 'Adding...';

        try {
            const newApp = await AppService.createApp({ name: appName, type: appType });
            handleRequiresInstallation(newApp);
            alert(`App "${newApp.name}" created successfully!`);
        } catch (error) {
            console.error('Failed to create app:', error);
            alert('Failed to create app. Please try again.');
        } finally {
            submitButton.disabled = false;
            submitButton.textContent = originalText;
        }
    };

    setTimeout(() => {
        const form = document.getElementById('wp2-add-app-form');
        if (form) {
            form.addEventListener('submit', handleFormSubmit);
        }
    }, 0);

    return `
        <div class="wp2-wizard-content">
            <h2>Add New App</h2>
            <form id="wp2-add-app-form">
                <label for="wp2-app-name">App Name</label>
                <input type="text" id="wp2-app-name" name="appName" required />

                <label for="wp2-app-type">App Type</label>
                <select id="wp2-app-type" name="appType">
                    <option value="user">User</option>
                    <option value="organization">Organization</option>
                </select>

                <button type="submit" class="wp2-btn wp2-btn-primary">Add App</button>
            </form>
        </div>
    `;
};