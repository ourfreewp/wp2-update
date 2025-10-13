export const ManualCredentialsModal = () => `
    <div class="wp2-modal-header">
        <h2>Enter Manual Credentials</h2>
    </div>
    <div class="wp2-modal-body">
        <form class="wp2-form">
            <p>Enter the credentials for your GitHub App.</p>
            <input type="text" name="app_id" placeholder="App ID" class="wp2-input" required />
            <input type="text" name="installation_id" placeholder="Installation ID" class="wp2-input" required />
            <textarea name="private_key" placeholder="Private Key" class="wp2-input" rows="5" required></textarea>
            <div class="wp2-modal-actions">
                <button type="submit" class="wp2-btn wp2-btn--primary">Save</button>
            </div>
        </form>
    </div>
`;
