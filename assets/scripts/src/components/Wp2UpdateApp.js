import { LitElement, html, css } from 'lit';
import { customElement } from 'lit/decorators.js';
import { store, updateState } from '../state/store.js';
import { StoreController } from '@nanostores/lit';
import { syncAllPackages } from '../actions/packageActions.js';

// Import the new modal manager and notification center
import './ModalManager.js';
import './NotificationCenter.js';
import '../components/shared/UiButton.js';

// Import the router
import '../router/Router.js';

@customElement('wp2-update-app')
export class Wp2UpdateApp extends LitElement {
  createRenderRoot() {
    return this; // Disable shadow DOM to use global Bootstrap styles
  }

  store = new StoreController(this, store);

  syncAll() {
    syncAllPackages();
  }

  render() {
    const state = store.get();
    const flags = state.flags || {};
    const banners = [];
    if (flags.headless) {
      banners.push(html`<div class="alert alert-warning mb-3" role="status">
        <strong>Headless mode:</strong> Admin UI actions are limited; REST/CLI only. Disable <code>WP2_UPDATE_HEADLESS</code> to restore full interface.
      </div>`);
    }
    if (flags.devMode) {
      banners.push(html`<div class="alert alert-info mb-3" role="status">
        <strong>Developer mode enabled.</strong> Remote updates are skipped to honor local overrides.
      </div>`);
    }

    return html`
      <div class="wp2-header d-flex flex-column flex-md-row align-items-md-center justify-content-md-between mb-4">
        <div>
          <h1 class="wp2-main-title">WP2 Update</h1>
          <p>Manage your GitHub-hosted plugins and themes with clarity, confidence, and control.</p>
        </div>
        <div class="d-flex gap-2 mt-2 mt-md-0">
          ${window.wp2UpdateData?.caps?.restoreBackups ? html`
            <ui-button
              text="Backups"
              variant="outline-secondary"
              @click="${() => { window.location.hash = '#/backups'; }}"
            ></ui-button>
          `: ''}
          ${window.wp2UpdateData?.caps?.manage ? html`
            <ui-button
              text="Config"
              variant="outline-secondary"
              @click="${() => { window.location.hash = '#/config'; }}"
            ></ui-button>
          ` : ''}
          <ui-button
            text="Create Package"
            variant="success"
            @click="${() => updateState({ activeModal: 'createPackage' })}"
          ></ui-button>
          <ui-button
            text="Add GitHub App"
            variant="primary"
            @click="${() => updateState({ activeModal: 'createApp' })}"
          ></ui-button>
          <ui-button
            text="Sync All"
            variant="outline-secondary"
            @click="${this.syncAll}"
          ></ui-button>
        </div>
      </div>
      <!-- Visually hidden aria-live region for dynamic status updates -->
      <div id="wp2-status-live" aria-live="polite" style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden;">${store.get().statusMessage || ''}</div>
      ${banners}
      <wp2-router></wp2-router>
      <modal-manager></modal-manager>
      <notification-center></notification-center>
    `;
  }

  _setActiveTab(tab) {
    updateState({ activeTab: tab });
    const url = new URL(window.location);
    url.searchParams.set('tab', tab);
    window.history.replaceState({}, '', url);
  }

  _handleKeydown(event, tab) {
    if (event.key === 'Enter' || event.key === ' ') {
      this._setActiveTab(tab);
    }
  }
}
