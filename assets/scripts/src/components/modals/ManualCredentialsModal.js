import { LitElement, html, css } from 'lit';
import { saveManualCredentials } from '../../actions/appActions.js';

class ManualCredentialsModal extends LitElement {
    static properties = {
        formData: { type: Object },
        isSaving: { type: Boolean },
    };

    constructor() {
        super();
        this.formData = {
            app_id: '',
            installation_id: '',
            private_key: '',
        };
        this.isSaving = false;
    }


    createRenderRoot() {
        return this; // Disable shadow DOM
    }

    render() {
        return html`
            <div class="wp2-modal" role="dialog" aria-modal="true">
                <div class="wp2-modal-header">
                    <h2>Enter Manual Credentials</h2>
                </div>
                <div class="wp2-modal-body">
                    <form id="manual-credentials-form" class="wp2-form" @submit="${this._saveCredentials}">
                        <p>Enter the credentials for your GitHub App.</p>
                        <div>
                            <label for="app-id" class="form-label">App ID:</label>
                            <input type="text" id="app-id" .value="${this.formData.app_id}" @input="${(e) => this._updateField('app_id', e.target.value)}" class="wp2-input" required />
                        </div>
                        <div>
                            <label for="installation-id" class="form-label">Installation ID:</label>
                            <input type="text" id="installation-id" .value="${this.formData.installation_id}" @input="${(e) => this._updateField('installation_id', e.target.value)}" class="wp2-input" required />
                        </div>
                        <div>
                            <label for="private-key" class="form-label">Private Key:</label>
                            <textarea id="private-key" .value="${this.formData.private_key}" @input="${(e) => this._updateField('private_key', e.target.value)}" class="wp2-input" rows="5" required></textarea>
                        </div>
                    </form>
                </div>
                <div class="wp2-modal-footer">
                    <button class="wp2-btn wp2-btn--secondary" @click="${this._closeModal}">Cancel</button>
                    <button class="wp2-btn wp2-btn--primary" ?disabled="${this.isSaving}" @click="${this._saveCredentials}">
                        ${this.isSaving ? 'Saving...' : 'Save'}
                    </button>
                </div>
            </div>
        `;
    }

    _updateField(field, value) {
        this.formData = { ...this.formData, [field]: value };
    }

    async _saveCredentials(e) {
        e.preventDefault();
        const form = this.shadowRoot.getElementById('manual-credentials-form');
        if (!form.checkValidity()) {
            form.classList.add('was-validated');
            return;
        }

        this.isSaving = true;
        try {
            await saveManualCredentials(this.formData);
            this._closeModal();
        } catch (error) {
            console.error('Failed to save credentials:', error);
        } finally {
            this.isSaving = false;
        }
    }

    _closeModal() {
        const event = new CustomEvent('close-modal', { bubbles: true, composed: true });
        this.dispatchEvent(event);
    }
}

customElements.define('manual-credentials-modal', ManualCredentialsModal);
