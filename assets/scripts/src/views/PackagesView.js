import { LitElement, html } from 'lit';
import { customElement } from 'lit/decorators.js';
import { store } from '../state/store.js';
import { StoreController } from '@nanostores/lit';
import { updateState } from '../state/store.js';

import '../components/package/PackageRow.js';

@customElement('packages-view')
export class PackagesView extends LitElement {
  store = new StoreController(this, store);

  render() {
    const { packages } = this.store.value;
    return html`
      <div class="wrap">
        <button @click=${() => updateState({ activeModal: 'createPackage' })} class="btn btn-primary mb-3">Add Package</button>
        <div class="list-group">
          ${packages.map(
            (pkg) => html`<package-row .pkg=${pkg}></package-row>`
          )}
        </div>
      </div>
    `;
  }

  createRenderRoot() {
    return this; // Disable shadow DOM
  }
}
