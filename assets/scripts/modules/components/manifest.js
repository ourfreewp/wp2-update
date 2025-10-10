const { __ } = wp.i18n;

export const render_configure_manifest = (draft) => {
    const container = document.getElementById('configure-manifest');
    if (!container) return;

    // If the form already exists, don't re-render it.
    // This prevents the input fields from being reset while the user is typing.
    if (container.querySelector('#wp2-manifest-config-form')) {
        const orgRow = container.querySelector('#wp2-org-name-row');
        if (orgRow) {
            orgRow.style.display = draft.accountType === 'organization' ? 'block' : 'none';
        }
        return;
    }

    container.innerHTML = `
        <h2 class="wp2-step-title">${__('1. Configure GitHub App', 'wp2-update')}</h2>
        <form id="wp2-manifest-config-form" class="wp2-form-container">
            <div class="wp2-form-row">
                <label for="wp2-encryption-key" class="wp2-label">${__('Encryption Key', 'wp2-update')}</label>
                <input type="password" id="wp2-encryption-key" name="encryption-key" class="wp2-input" autocomplete=off required placeholder="${__('A strong, unique key to secure your credentials', 'wp2-update')}">
                <p class="description" style="margin-top: 8px;">${__('This key is required to encrypt sensitive data. Generate a new one if you don\'t have one.', 'wp2-update')}</p>
            </div>
            <div class="wp2-form-row">
                <label for="wp2-app-name" class="wp2-label">${__('App Name', 'wp2-update')}</label>
                <input type="text" id="wp2-app-name" name="app-name" class="wp2-input" required value="${draft.name || ''}">
            </div>
            <div class="wp2-form-row">
                <label for="wp2-app-type" class="wp2-label">${__('Account Type', 'wp2-update')}</label>
                <select id="wp2-app-type" name="app-type" class="wp2-select">
                    <option value="user" ${draft.accountType === 'user' ? 'selected' : ''}>${__('User', 'wp2-update')}</option>
                    <option value="organization" ${draft.accountType === 'organization' ? 'selected' : ''}>${__('Organization', 'wp2-update')}</option>
                </select>
            </div>
            <div id="wp2-org-name-row" class="wp2-form-row" style="display: ${draft.accountType === 'organization' ? 'block' : 'none'};">
                <label for="wp2-organization" class="wp2-label">${__('Organization Name (Username)', 'wp2-update')}</label>
                <input type="text" id="wp2-organization" name="organization" class="wp2-input" placeholder="e.g., 'my-company-llc'" value="${draft.organization || ''}">
            </div>
            <div class="wp2-form-footer">
                <button type="submit" class="wp2-button wp2-button-primary" data-wp2-action="submitManifestConfig" ${!draft.name ? 'disabled' : ''}>
                    ${__('Save and Continue to GitHub', 'wp2-update')}
                </button>
            </div>
        </form>
    `;
};