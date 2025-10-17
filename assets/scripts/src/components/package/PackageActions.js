import { LitElement, html } from 'lit';
import { customElement, property } from 'lit/decorators.js';
import { updateState } from '../../state/store.js';

@customElement('package-actions')
export class PackageActions extends LitElement {
  @property({ type: Object }) pkg;

  render() {
    return html`
      <div>
        <button class="btn btn-sm btn-secondary" @click=${this._viewDetails}>Details</button>
        <button class="btn btn-sm btn-warning" @click=${this._rollback}>Rollback</button>
        ${this.pkg.app_id ? '' : html`<button class="btn btn-sm btn-info" @click=${this._assignApp}>Assign App</button>`}
      </div>
    `;
  }

  _viewDetails() {
    updateState({ 
      activeModal: 'packageDetails', 
      modalProps: { packageRepo: this.pkg.repo } 
    });
  }

  _rollback() {
    updateState({ 
      activeModal: 'rollbackPackage', 
      modalProps: { pkg: this.pkg } 
    });
  }

  _assignApp() {
    updateState({ 
      activeModal: 'assignApp', 
      modalProps: { packageId: this.pkg.id } 
    });
  }

  createRenderRoot() {
    return this;
  }
}
