
import { LitElement, html } from 'lit';
import { customElement, property } from 'lit/decorators.js';
import { disconnectApp } from '../../actions/appActions.js';

@customElement('app-details-modal')
export class AppDetailsModal extends LitElement {
    @property({ type: Object }) app = {};

    render() {
        const { app } = this;
        if (!app) return html``;
        return html`
            <div class="modal">
                <div class="modal-header">
                    <h2>${app.name}</h2>
                </div>
                <div class="modal-body">
                    <dl class="wp2-detail-grid">
                        <dt>Account Type</dt><dd>${app.account_type}</dd>
                        <dt>App ID</dt><dd>${app.app_id}</dd>
                        <dt>Installation ID</dt><dd>${app.installation_id}</dd>
                    </dl>
                </div>
                <div class="modal-footer">
                    <button class="wp2-btn--danger" @click=${this._handleDisconnect}>Disconnect App</button>
                    <button class="wp2-btn--secondary" @click=${this._closeModal}>Close</button>
                </div>
            </div>
        `;
    }

    _handleDisconnect() {
        if (this.app && this.app.app_id) {
            disconnectApp(this.app.app_id);
        } else {
            console.error('App ID not found.');
        }
    }

    _closeModal() {
        this.dispatchEvent(new CustomEvent('close', { bubbles: true, composed: true }));
    }

    createRenderRoot() {
        return this; // Disable shadow DOM for global styles
    }
}
