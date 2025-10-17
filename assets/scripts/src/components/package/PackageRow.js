import { LitElement, html } from 'lit';
import { customElement, property } from 'lit/decorators.js';
import { syncPackage, updatePackage, assignAppToPackage } from '../../actions/packageActions.js';
import { setPackageProgress } from '../../state/store.js';

@customElement('package-row')
export class PackageRow extends LitElement {
  @property({ type: Object }) pkg;

  render() {
    return html`
      <div class="list-group-item">
        <div class="d-flex w-100 justify-content-between">
          <h5 class="mb-1">${this.pkg.name}</h5>
          <small>Status: ${this.pkg.status}</small>
        </div>
        <p class="mb-1">Version: ${this.pkg.version}</p>
        <div class="d-flex justify-content-end">
          <button @click=${this._syncPackage} class="btn btn-sm btn-primary me-2" ?disabled=${this.pkg.isUpdating}>Sync</button>
          <button @click=${this._updatePackage} class="btn btn-sm btn-success me-2" ?disabled=${this.pkg.isUpdating}>Update</button>
          <button @click=${this._openAssignAppModal} class="btn btn-sm btn-warning">Assign App</button>
        </div>
      </div>
    `;
  }

  _syncPackage() {
    syncPackage(this.pkg.id);
  }

  _updatePackage() {
    setPackageProgress(this.pkg.id, 'update', true);
    updatePackage(this.pkg.id).finally(() => {
        setPackageProgress(this.pkg.id, 'update', false);
    });
  }

  _openAssignAppModal() {
    const event = new CustomEvent('open-assign-app-modal', {
      detail: { packageId: this.pkg.id },
      bubbles: true,
      composed: true,
    });
    this.dispatchEvent(event);
  }

  createRenderRoot() {
    return this;
  }
}
