export const WizardModal = () => `
    <div class="wp2-modal-header">
        <h2>Add GitHub App</h2>
    </div>
    <div class="wp2-modal-body">
        <form class="wp2-form">
            <p>Generate a manifest to connect a new GitHub App for this site.</p>
            <input type="text" name="app_name" placeholder="App Name" class="wp2-input" required />
            <input type="password" name="encryption_key" placeholder="Encryption Key (min. 16 chars)" class="wp2-input" minlength="16" required />
            <div class="wp2-modal-actions">
                <button type="submit" class="wp2-btn wp2-btn--primary">Generate Manifest</button>
            </div>
        </form>
    </div>
`;
