import { LitElement, html, css } from 'lit';
import { customElement, state } from 'lit/decorators.js';
import { fetchBackups, deleteBackups as deleteBackupsAction, deleteBackup as deleteBackupAction } from '../actions/backupActions.js';

@customElement('backups-view')
export class BackupsView extends LitElement {
  @state() backups = [];
  @state() selected = [];
  @state() query = '';
  @state() loading = false;

  createRenderRoot() { return this; }

  connectedCallback() {
    super.connectedCallback();
    this._onRestored = () => this.load();
    window.addEventListener('wp2:backups:restored', this._onRestored);
    this.load();
  }

  async load() {
    this.loading = true;
    try {
      this.backups = await fetchBackups(this.query);
      // prune selected files that no longer exist
      const files = new Set(this.backups.map(b => b.file));
      this.selected = this.selected.filter(file => files.has(file));
    } finally {
      this.loading = false;
    }
  }

  onSearch(e) {
    this.query = e.target.value;
    this.load();
  }

  typeFromName(name) {
    if (name.startsWith('plugin-')) return 'plugin';
    if (name.startsWith('theme-')) return 'theme';
    return 'plugin';
  }

  async onRestore(file) {
    const type = this.typeFromName(file);
    // Open modal via global modal manager
    const { updateState } = await import('../state/store.js');
    updateState({ activeModal: 'restoreBackup', modalProps: { file, type } });
  }

  async onDownload(file) {
    try {
      const apiRoot = window.wp2UpdateData?.apiRoot?.replace(/\/$/, '') || '';
      const url = `${apiRoot}/backups/download?file=${encodeURIComponent(file)}`;
      const res = await fetch(url, { credentials: 'same-origin', headers: { 'X-WP-Nonce': window.wp2UpdateData?.nonce } });
      if (!res.ok) throw new Error('Download failed');
      const blob = await res.blob();
      const a = document.createElement('a');
      const obj = URL.createObjectURL(blob);
      a.href = obj;
      a.download = file;
      document.body.appendChild(a);
      a.click();
      a.remove();
      URL.revokeObjectURL(obj);
    } catch (e) {
      console.error('Download failed', e);
      alert('Download failed');
    }
  }

  toggle(file, checked) {
    const set = new Set(this.selected);
    if (checked) {
      set.add(file);
    } else {
      set.delete(file);
    }
    this.selected = Array.from(set);
  }

  selectAll(checked) {
    if (checked) {
      this.selected = this.backups.map(b => b.file);
    } else {
      this.selected = [];
    }
  }

  async deleteSelected() {
    if (!this.selected.length) return;
    if (!confirm(`Delete ${this.selected.length} selected backup(s)?`)) return;
    await deleteBackupsAction(this.selected);
    this.selected = [];
    this.load();
  }

  async onDelete(file) {
    if (!confirm(`Delete backup ${file}?`)) return;
    await deleteBackupAction(file);
    this.selected = this.selected.filter(f => f !== file);
    this.load();
  }

  disconnectedCallback() {
    window.removeEventListener('wp2:backups:restored', this._onRestored);
    super.disconnectedCallback();
  }

  render() {
    return html`
      <div class="d-flex align-items-center justify-content-between mb-3">
        <h2 class="h4 m-0">Backups</h2>
        <input class="form-control" style="max-width:260px" placeholder="Filter backups" @input=${this.onSearch.bind(this)} />
      </div>
      ${this.loading ? html`<div>Loading…</div>` : ''}
      <div class="d-flex align-items-center justify-content-between mb-2">
        <div>
          <button class="btn btn-sm btn-outline-danger" ?disabled=${this.selected.length===0} @click=${this.deleteSelected.bind(this)}>Delete Selected</button>
        </div>
        <div class="text-muted small">${this.selected.length} selected</div>
      </div>
      <div class="table-responsive">
        <table class="table table-sm align-middle">
          <thead>
            <tr>
              <th style="width:32px"><input type="checkbox" @change=${(e) => this.selectAll(e.target.checked)} /></th>
              <th>File</th>
              <th>Size</th>
              <th>Modified</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            ${this.backups.map(b => html`
              <tr>
                <td><input type="checkbox" .checked=${this.selected.includes(b.file)} @change=${(e) => this.toggle(b.file, e.target.checked)} /></td>
                <td>${b.file}</td>
                <td>${(b.size/1024/1024).toFixed(2)} MB</td>
                <td>${new Date(b.modified).toLocaleString()}</td>
                <td class="text-end">
                  <div class="btn-group">
                    <button class="btn btn-outline-secondary btn-sm" @click=${() => this.onDownload(b.file)}>Download</button>
                    <button class="btn btn-outline-danger btn-sm" @click=${() => this.onRestore(b.file)}>Restore</button>
                    <button class="btn btn-outline-danger btn-sm" @click=${() => this.onDelete(b.file)}>Delete</button>
                  </div>
                </td>
              </tr>
            `)}
            ${this.backups.length === 0 ? html`<tr><td colspan="5" class="text-muted">No backups found.</td></tr>`: ''}
          </tbody>
        </table>
      </div>
    `;
  }

  static styles = css``;
}
