import { LitElement, html } from 'lit';
import { customElement, state } from 'lit/decorators.js';
import { HealthService } from '../services/HealthService.js';
import { StoreController } from '@nanostores/lit';
import { store } from '../state/store.js';
import '../components/shared/UiButton.js'; // Import the button

@customElement('health-view')
export class HealthView extends LitElement {
  store = new StoreController(this, store);
  @state() _isLoading = false; // Add local loading state

  connectedCallback() {
    super.connectedCallback();
    HealthService.fetchHealthStatus(); // Fetch initial health data
  }

  render() {
    const { health } = this.store.value;
    return html`
      <div id="health-container">
        ${health ? html`<pre>${JSON.stringify(health, null, 2)}</pre>` : html`<p>Loading health data...</p>`}

        <ui-button
          text="Refresh Health"
          .loading=${this._isLoading}
          @click=${this._refreshHealth}
        ></ui-button>
      </div>
    `;
  }

  async _refreshHealth() {
    this._isLoading = true;
    await HealthService.refreshHealthStatus();
    this._isLoading = false;
  }

  createRenderRoot() {
    return this; // Disable shadow DOM
  }
}
