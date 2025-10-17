import { LitElement, html } from 'lit';
import { customElement } from 'lit/decorators.js';
import { updateState } from '../../state/store.js';

@customElement('apps-header')
export class AppsHeader extends LitElement {


    createRenderRoot() {
        return this; // Disable shadow DOM
    }
  render() {
    return html`
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h1>Applications</h1>
        <button @click=${this._addApp} class="btn btn-primary">Add App</button>
      </div>
    `;
  }

  _addApp() {
    updateState({ activeModal: 'createApp' });
  }

  createRenderRoot() { return this; }
}