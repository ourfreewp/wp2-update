const { __ } = wp.i18n;

export const WizardModal = () => `
    <div class="wp2-modal-header">
        <h2>${__('Add GitHub App', 'wp2-update')}</h2>
    </div>
    <div class="wp2-modal-body">
        <form class="wp2-form">
            <p>${__('Generate a manifest to connect a new GitHub App for this site.', 'wp2-update')}</p>
            <input type="text" name="app_name" placeholder="${__('App Name', 'wp2-update')}" class="wp2-input" required />
            <input type="password" name="encryption_key" placeholder="${__('Encryption Key (min. 16 chars)', 'wp2-update')}" class="wp2-input" minlength="16" required />
            <div class="wp2-modal-actions">
                <button type="submit" class="wp2-btn wp2-btn--primary">${__('Generate Manifest', 'wp2-update')}</button>
            </div>
        </form>
    </div>
`;
