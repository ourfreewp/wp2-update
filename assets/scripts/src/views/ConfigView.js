import { LitElement, html, css } from 'lit';
import { customElement, state } from 'lit/decorators.js';
import { ConfigService } from '../services/ConfigService.js';
import { NotificationService } from '../services/NotificationService.js';

@customElement('config-view')
export class ConfigView extends LitElement {
  @state() uploading = false;
  @state() lastImportResult = null;

  createRenderRoot() {
    return this;
  }

  static styles = css``;

  async onExport() {
    try {
      await ConfigService.downloadConfig();
      NotificationService.showSuccess('Configuration export started.');
    } catch (e) {
      NotificationService.showError(e?.message || 'Export failed.');
    }
  }

  async onImport(event) {
    event.preventDefault();
    const fileInput = this.querySelector('#config-import-file');
    const file = fileInput?.files?.[0];
    if (!file) {
      NotificationService.showError('Select a JSON file to import.');
      return;
    }
    this.uploading = true;
    try {
      const response = await ConfigService.importConfig(file);
      this.lastImportResult = response?.data ?? response;
      NotificationService.showSuccess('Configuration imported successfully.');
      fileInput.value = '';
    } catch (e) {
      NotificationService.showError(e?.message || 'Import failed.');
    } finally {
      this.uploading = false;
    }
  }

  renderResult() {
    if (!this.lastImportResult) {
      return null;
    }
    const updated = this.lastImportResult.updated || [];
    return html`
      <div class="alert alert-success mt-3" role="status">
        <strong>Updated options:</strong> ${updated.length ? updated.join(', ') : 'None'}
      </div>
    `;
  }

  render() {
    return html`
      <div class="row g-4">
        <div class="col-lg-6">
          <div class="card h-100 shadow-sm">
            <div class="card-header bg-light">
              <h2 class="h5 mb-0">Export Configuration</h2>
            </div>
            <div class="card-body">
              <p class="text-muted">Download a JSON snapshot of GitHub Apps, packages, and settings. Secrets are never included.</p>
              <button class="btn btn-primary" @click=${this.onExport}>Download wp2-config.json</button>
            </div>
          </div>
        </div>
        <div class="col-lg-6">
          <div class="card h-100 shadow-sm">
            <div class="card-header bg-light">
              <h2 class="h5 mb-0">Import Configuration</h2>
            </div>
            <div class="card-body">
              <p class="text-muted">Upload a previously exported configuration to replicate environment settings.</p>
              <form @submit=${this.onImport}>
                <div class="mb-3">
                  <input id="config-import-file" type="file" class="form-control" accept="application/json" ?disabled=${this.uploading} required>
                </div>
                <button class="btn btn-outline-primary" type="submit" ?disabled=${this.uploading}>
                  ${this.uploading ? html`<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Importing...` : 'Import Settings'}
                </button>
              </form>
              ${this.renderResult()}
            </div>
          </div>
        </div>
      </div>
      <div class="alert alert-info mt-4" role="status">
        <strong>Heads up:</strong> Private keys and other secrets must be re-uploaded after import.
      </div>
    `;
  }
}
