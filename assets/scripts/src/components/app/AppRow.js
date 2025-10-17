import { LitElement, html } from 'lit';
import { customElement, property } from 'lit/decorators.js';
import { updateState } from '../../state/store.js';
import { deleteApp } from '../../actions/appActions.js';

@customElement('app-row')
export class AppRow extends LitElement {

  @property({ type: Object }) app;

  render() {
    return html`
      <div class="list-group-item d-flex justify-content-between align-items-center">
        <span>${this.app.name}</span>
        <div>
          <button @click=${this._viewDetails} class="btn btn-sm btn-info">Details</button>
          <button @click=${this._deleteApp} class="btn btn-sm btn-danger">Delete</button>
        </div>
      </div>
    `;
  }

  _viewDetails() {
    updateState({ 
      activeModal: 'appDetails', 
      modalProps: { appId: this.app.id } 
    });
  }

  _deleteApp() {
    deleteApp(this.app.id, this.app.name);
  }

  createRenderRoot() {
    return this;
  }
}
