import { LitElement, html } from 'lit';
import { customElement, property } from 'lit/decorators.js';
import { rollbackPackage } from '../../actions/packageActions.js';
import '../package/ReleaseDropdown.js';
import '../shared/UiModal.js';

@customElement('rollback-modal')
export class RollbackModal extends LitElement {
  @property({ type: Object }) pkg;

  render() {
    const { pkg } = this;
    if (!pkg) return html``;

    return html`
      <ui-modal .title="Rollback ${pkg.name}" .isOpen=${true} @close=${this._closeModal}>
        <div slot="body">
          <p>Select a version to roll back to.</p>
          <release-dropdown .pkg=${pkg}></release-dropdown>
        </div>
        <div slot="footer">
          <button type="button" class="btn btn-secondary" @click=${this._closeModal}>Cancel</button>
          <button type="button" class="btn btn-danger" @click=${this._handleConfirm}>Confirm Rollback</button>
        </div>
      </ui-modal>
    `;
  }

  _handleConfirm() {
    const select = this.renderRoot.querySelector('release-dropdown');
    if (select && select.value) {
      rollbackPackage(this.pkg.repo, select.value);
    }
  }

  _closeModal() {
    this.dispatchEvent(new CustomEvent('close', { bubbles: true, composed: true }));
  }

  createRenderRoot() {
    return this; // Disable shadow DOM
  }
}
