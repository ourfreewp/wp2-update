export const render_configure_manifest = () => {
    const container = document.querySelector('#configure-manifest');
    if (!container) return;

    container.innerHTML = `
        <h2>${__('Configure GitHub App', 'wp2-update')}</h2>
        <form id="manifest-config-form">
            <p>${__('Enter the required details to generate a secure installation manifest.', 'wp2-update')}</p>
            <label for="app-name">${__('App Name', 'wp2-update')}</label>
            <input type="text" id="app-name" name="app-name" required value="${window.wp2UpdateAppState.manifest?.name || ''}"/>

            <label for="organization">${__('Organization (Optional)', 'wp2-update')}</label>
            <input type="text" id="organization" name="organization" value="${window.wp2UpdateAppState.manifest?.org || ''}"/>

            <button type="submit" class="button button-primary" data-action="submit-manifest-config">${__('Save and Continue to GitHub', 'wp2-update')}</button>
        </form>
    `;
};