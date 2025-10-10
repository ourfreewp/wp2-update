import { escapeHTML } from '../../utils.js';

const { __ } = window.wp?.i18n ?? { __: (text) => text };

export const configuringView = (state) => {
    const draft = state.manifestDraft;
    return `
        <section class="wp2-dashboard-card" role="region" aria-labelledby="configuring-heading">
            <h1 id="configuring-heading" class="screen-reader-text">${__('Configuring GitHub App', 'wp2-update')}</h1>
            <h2>${__('Create Your GitHub App', 'wp2-update')}</h2>
            <p class="wp2-muted">${__('We pre-fill the manifest for you. Review the details, then continue to GitHub to finish the setup.', 'wp2-update')}</p>
            <form id="wp2-configure-form" class="wp2-form">
                <label class="wp2-label">${__('Encryption Key', 'wp2-update')}
                    <input type="password" name="encryption-key" id="wp2-encryption-key" class="wp2-input" placeholder="${__('Minimum 16 characters', 'wp2-update')}" required autocomplete="off" value="${escapeHTML(draft.encryptionKey ?? '')}" />
                </label>
                <label class="wp2-label">${__('App Name', 'wp2-update')}
                    <input type="text" name="app-name" id="wp2-app-name" class="wp2-input" value="${escapeHTML(draft.name ?? '')}" required />
                </label>
                <label class="wp2-label">${__('Account Type', 'wp2-update')}
                    <select name="app-type" id="wp2-app-type" class="wp2-select">
                        <option value="user" ${draft.accountType === 'user' ? 'selected' : ''}>${__('Personal User', 'wp2-update')}</option>
                        <option value="organization" ${draft.accountType === 'organization' ? 'selected' : ''}>${__('Organization', 'wp2-update')}</option>
                    </select>
                </label>
                <div class="wp2-org-field" ${draft.accountType === 'organization' ? '' : 'hidden'}>
                    <label class="wp2-label">${__('Organization Slug', 'wp2-update')}
                        <input type="text" name="organization" id="wp2-organization" class="wp2-input" placeholder="example-org" value="${escapeHTML(draft.organization ?? '')}" />
                    </label>
                </div>
                <label class="wp2-label">${__('Manifest JSON', 'wp2-update')}
                    <textarea name="manifest" id="wp2-manifest-json" class="wp2-input wp2-code" rows="10">${escapeHTML(draft.manifestJson)}</textarea>
                </label>
                <div class="wp2-form-actions">
                    <button type="button" id="wp2-cancel-config" class="wp2-button wp2-button-secondary">${__('Cancel', 'wp2-update')}</button>
                    <button type="submit" class="wp2-button wp2-button-primary">${__('Save and Continue to GitHub', 'wp2-update')}</button>
                </div>
            </form>
        </section>
    `;
};
