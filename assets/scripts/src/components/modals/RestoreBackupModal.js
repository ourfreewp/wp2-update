import { LitElement, html } from 'lit';
import { customElement, property } from 'lit/decorators.js';
import { updateState } from '../../state/store.js';
import { restoreBackup } from '../../actions/backupActions.js';

@customElement('restore-backup-modal')
export class RestoreBackupModal extends LitElement {
  @property({ type: Object }) props = {};

  createRenderRoot() { return this; }

  close() {
    updateState({ activeModal: null, modalProps: {} });
  }

  async onConfirm() {
    const { file, type } = this.props || {};
    await restoreBackup(file, type);
    window.dispatchEvent(new CustomEvent('wp2:backups:restored', { detail: { file, type } }));
    this.close();
  }

  render() {
    const { file } = this.props || {};
    return html`
      <div class="modal show" style="display:block" tabindex="-1" role="dialog" aria-modal="true">
        <div class="modal-dialog">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">Restore Backup</h5>
              <button type="button" class="btn-close" @click=${this.close} aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <p>Are you sure you want to restore <strong>${file}</strong>? This will overwrite current files.</p>
            </div>
            <div class="modal-footer">
              <button class="btn btn-secondary" @click=${this.close}>Cancel</button>
              <button class="btn btn-danger" @click=${this.onConfirm.bind(this)}>Restore</button>
            </div>
          </div>
        </div>
      </div>
    `;
  }
}

