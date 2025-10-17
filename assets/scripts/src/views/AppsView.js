import { LitElement, html } from 'lit';
import { customElement } from 'lit/decorators.js';
import { store } from '../state/store.js';
import { StoreController } from '@nanostores/lit';
import { updateState } from '../state/store.js';
import { fetchApps } from '../actions/appActions';

// Import header and row components
import '../components/app/AppsHeader.js';
import '../components/app/AppRow.js';

@customElement('apps-view')
export class AppsView extends LitElement {
  store = new StoreController(this, store);

  render() {
    const { apps } = this.store.value || {}; // Ensure store.value is defined
    const appsList = Array.isArray(apps) ? apps : []; // Ensure apps is an array

    return html`
      <div class="wrap">
        <apps-header></apps-header>
        <button @click=${this._addApp} class="btn btn-primary mb-3">Add App</button>
        <div class="list-group">
          ${appsList.map((app) => html`<app-row .app=${app}></app-row>`)} <!-- Render app rows -->
        </div>
      </div>
    `;
  }

  _addApp() {
    updateState({ activeModal: 'createApp' });
  }

  createRenderRoot() {
    return this; // Disable shadow DOM
  }

  connectedCallback() {
    super.connectedCallback();
    fetchApps(); // Updated to use appActions.js
  }
}
