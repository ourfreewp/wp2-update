import { LitElement, html } from 'lit';
import { customElement } from 'lit/decorators.js';
import { store } from '../state/store.js';
import { StoreController } from '@nanostores/lit';
import { updateState } from '../state/store.js';
import { fetchApps } from '../actions/appActions.js';
import { translations } from '../i18n/translations.js';

// Import header and row components
import '../components/app/AppsHeader.js';
import '../components/app/AppRow.js';

@customElement('apps-view')
export class AppsView extends LitElement {
  store = new StoreController(this, store);

  render() {
    const { apps } = this.store.value || {};
    const appsList = Array.isArray(apps) ? apps : [];

    return html`
      <div class="wrap" role="main">
        <apps-header></apps-header>
        <button @click=${this._addApp} class="btn btn-primary mb-3">${translations.addApp}</button>
        ${appsList.length === 0
          ? html`<p class="text-muted" role="status">${translations.noApps(appsList.length)}</p>`
          : html`<div class="list-group" role="list">
              ${appsList.map((app) => html`<app-row .app=${app} role="listitem"></app-row>`)}
            </div>`}
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
    fetchApps();
  }
}
