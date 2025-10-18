import { LitElement, html } from 'lit';
import { customElement, property } from 'lit/decorators.js';
import { rollbackPackage } from '../../actions/packageActions.js';
import '../package/ReleaseDropdown.js';
import '../shared/UiModal.js';

@customElement('rollback-modal')
export class RollbackModal extends LitElement {
  @property({ type: Object }) pkg;
  @property({ type: Boolean }) isSaving = false;

  render() {
    const { pkg, isSaving } = this;
    if (!pkg) return html``;

    return html`
      <ui-modal .title="Rollback ${pkg.name}" .isOpen=${true} @close=${this._closeModal}>
        <div slot="body">
          <p>Select a version to roll back to.</p>
          <release-dropdown .pkg=${pkg}></release-dropdown>
        </div>
        <div slot="footer">
          <button type="button" class="btn btn-secondary" @click=${this._closeModal} ?disabled=${isSaving}>Cancel</button>
          <button type="button" class="btn btn-danger" @click=${this._handleConfirm} ?disabled=${isSaving}>
            ${isSaving ? 'Rolling back...' : 'Confirm Rollback'}
          </button>
        </div>
      </ui-modal>
    `;
  }

  async _handleConfirm() {
    const select = this.renderRoot.querySelector('release-dropdown');
    if (select && select.value) {
      this.isSaving = true;
      try {
        await rollbackPackage(this.pkg.repo, select.value);
      } finally {
        this.isSaving = false;
      }
    }
  }

  _closeModal() {
    this.dispatchEvent(new CustomEvent('close', { bubbles: true, composed: true }));
  }

  createRenderRoot() {
    return this; // Disable shadow DOM
  }
}
