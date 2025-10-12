import { escapeHtml } from '../../utils.js';

const { __ } = window.wp?.i18n ?? { __: (text) => text };

/**
 * Renders the "Configuring" view.
 * @param {Object} state - The current dashboard state.
 * @returns {string} - The rendered view markup.
 */
export function configuringView(state) {
    const draft = state.manifestDraft ?? {};
    const accountType = draft.accountType === 'organization' ? 'organization' : 'user';

    return `
        <div class="wp2-configuring">
            <h2>${__('Create GitHub App Manifest', 'wp2-update')}</h2>
            <p>${__('Provide the details below to generate the GitHub App manifest. Use the generated manifest when GitHub prompts for it.', 'wp2-update')}</p>
            <form id="wp2-configure-form" class="wp2-form">
                <div class="wp2-field-group">
                    <label for="wp2-encryption-key">${__('Encryption Key', 'wp2-update')}</label>
                    <input
                        type="text"
                        id="wp2-encryption-key"
                        name="encryption-key"
                        value="${escapeHtml(draft.encryptionKey ?? '')}"
                        minlength="16"
                        required
                    />
                    <p class="wp2-field-help">${__('Use a unique key with at least 16 characters. Store it securely.', 'wp2-update')}</p>
                </div>

                <div class="wp2-field-group">
                    <label for="wp2-app-name">${__('App Name', 'wp2-update')}</label>
                    <input
                        type="text"
                        id="wp2-app-name"
                        name="name"
                        value="${escapeHtml(draft.name ?? '')}"
                        required
                    />
                </div>

                <div class="wp2-field-group">
                    <label for="wp2-app-type">${__('Account Type', 'wp2-update')}</label>
                    <select id="wp2-app-type" name="account_type">
                        <option value="user"${accountType === 'user' ? ' selected' : ''}>${__('User', 'wp2-update')}</option>
                        <option value="organization"${accountType === 'organization' ? ' selected' : ''}>${__('Organization', 'wp2-update')}</option>
                    </select>
                </div>

                <div class="wp2-field-group wp2-org-field"${accountType === 'organization' ? '' : ' hidden'}>
                    <label for="wp2-organization">${__('Organization Slug', 'wp2-update')}</label>
                    <input
                        type="text"
                        id="wp2-organization"
                        name="organization"
                        value="${escapeHtml(draft.organization ?? '')}"
                        placeholder="${__('example-org', 'wp2-update')}"
                    />
                    <p class="wp2-field-help">${__('GitHub organization handle (optional for user apps).', 'wp2-update')}</p>
                </div>

                <div class="wp2-field-group">
                    <label for="wp2-manifest-json">${__('Manifest Overrides (JSON)', 'wp2-update')}</label>
                    <textarea
                        id="wp2-manifest-json"
                        name="manifest"
                        rows="10"
                        spellcheck="false"
                    >${escapeHtml(draft.manifestJson ?? '')}</textarea>
                    <p class="wp2-field-help">${__('You can tweak the generated manifest before sending it to GitHub.', 'wp2-update')}</p>
                </div>

                <div class="wp2-actions">
                    <button type="submit" class="wp2-button wp2-button--primary">
                        ${__('Generate Manifest', 'wp2-update')}
                    </button>
                    <button type="button" id="wp2-cancel-config" class="wp2-button">
                        ${__('Cancel', 'wp2-update')}
                    </button>
                </div>
            </form>
        </div>
    `;
}
