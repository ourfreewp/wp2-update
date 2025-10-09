const { __ } = wp.i18n;

export const render_configure_manifest = (draft) => {
    const container = document.querySelector('#configure-manifest');
    if (!container) return;

    try {
        container.innerHTML = `
            <h2>${__('Configure GitHub App', 'wp2-update')}</h2>
            <form id="manifest-config-form">
                <p>${__('Enter the required details to generate a secure installation manifest.', 'wp2-update')}</p>

                <label for="app-name">${__('App Name', 'wp2-update')}</label>
                <input type="text" id="app-name" name="app-name" required value="${draft.name || ''}"/>

                <label for="app-type">${__('Account Type', 'wp2-update')}</label>
                <select id="app-type" name="app-type">
                    <option value="user" ${draft.accountType === 'user' ? 'selected' : ''}>${__('User', 'wp2-update')}</option>
                    <option value="organization" ${draft.accountType === 'organization' ? 'selected' : ''}>${__('Organization', 'wp2-update')}</option>
                </select>

                <div id="org-name-row" style="display: ${draft.accountType === 'organization' ? 'block' : 'none'};">
                    <label for="organization">${__('Organization Name (Username)', 'wp2-update')}</label>
                    <input type="text" id="organization" name="organization" value="${draft.organization || ''}"/>
                </div>

                <textarea id="manifest-json" name="manifest-json" hidden>${JSON.stringify(draft)}</textarea>

                <button type="submit" class="button button-primary" data-action="submit-manifest-config">${__('Save and Continue to GitHub', 'wp2-update')}</button>
            </form>
        `;
    } catch (error) {
        console.error('Error rendering configure manifest:', error);
    }
};