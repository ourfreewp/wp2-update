import { LitElement, html, css } from 'lit';
import { customElement } from 'lit/decorators.js';
import { store, updateState } from '../state/store.js';
import { StoreController } from '@nanostores/lit';

// Import your view components
import '../views/DashboardView.js';
import '../views/HealthView.js';
import '../views/PackagesView.js';
import '../views/AppsView.js';

// Import the new modal manager and notification center
import './ModalManager.js';
import './NotificationCenter.js';

@customElement('wp2-update-app')
export class Wp2UpdateApp extends LitElement {
  createRenderRoot() {
    return this; // Disable shadow DOM to use global Bootstrap styles
  }

  store = new StoreController(this, store);

  render() {
    const state = store.get();

    return html`
      <div class="wp2-header d-flex flex-column flex-md-row align-items-md-center justify-content-md-between mb-4">
        <div>
          <h1 class="wp2-main-title">WP2 Update</h1>
          <p>Manage your GitHub-hosted plugins and themes with clarity, confidence, and control.</p>
        </div>
        <div class="d-flex gap-2 mt-2 mt-md-0">
          <button class="btn btn-success" @click="${() => updateState({ activeModal: 'createPackage' })}">
            <i class="bi bi-plus-lg me-1"></i> Create Package
          </button>
          <button class="btn btn-primary" @click="${() => updateState({ activeModal: 'createApp' })}">
            <i class="bi bi-github me-1"></i> Add GitHub App
          </button>
          <button id="sync-all-btn" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-repeat me-1"></i> Sync All
          </button>
        </div>
      </div>
      <nav class="nav nav-tabs" role="tablist">
        <a
          class="nav-link ${state.activeTab === 'dashboard' ? 'active' : ''}"
          role="tab"
          aria-selected="${state.activeTab === 'dashboard'}"
          @click=${() => this._setActiveTab('dashboard')}
        >
          Dashboard
        </a>
        <a
          class="nav-link ${state.activeTab === 'health' ? 'active' : ''}"
          role="tab"
          aria-selected="${state.activeTab === 'health'}"
          @click=${() => this._setActiveTab('health')}
        >
          Health
        </a>
        <a
          class="nav-link ${state.activeTab === 'packages' ? 'active' : ''}"
          role="tab"
          aria-selected="${state.activeTab === 'packages'}"
          @click=${() => this._setActiveTab('packages')}
        >
          Packages
        </a>
        <a
          class="nav-link ${state.activeTab === 'apps' ? 'active' : ''}"
          role="tab"
          aria-selected="${state.activeTab === 'apps'}"
          @click=${() => this._setActiveTab('apps')}
        >
          Apps
        </a>
      </nav>

      <div class="tab-content pt-4">
          ${state.activeTab === 'dashboard'
            ? html`<dashboard-view id="dashboard-panel" role="tabpanel"></dashboard-view>`
            : state.activeTab === 'health'
            ? html`<health-view id="health-panel" role="tabpanel"></health-view>`
            : state.activeTab === 'packages'
            ? html`<packages-view id="packages-panel" role="tabpanel"></packages-view>`
            : html`<apps-view id="apps-panel" role="tabpanel"></apps-view>`}
      </div>

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