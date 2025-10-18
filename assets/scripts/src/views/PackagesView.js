import { LitElement, html } from 'lit';
import { customElement, state } from 'lit/decorators.js';
import { store } from '../state/store.js';
import { StoreController } from '@nanostores/lit';
import { updateState } from '../state/store.js';
import { fetchPackages } from '../actions/packageActions.js';
import { translations } from '../i18n/translations.js';

import '../components/package/PackageRow.js';
import '../components/shared/UiButton.js';
import { withPackageService } from '../context/withServiceContext.js';
import { NotificationService } from '../services/NotificationService.js';

@customElement('packages-view')
export class PackagesView extends withPackageService(LitElement) {
  store = new StoreController(this, store);
  @state() bulkActionValue = 'update';
  @state() bulkChannelValue = 'stable';

  connectedCallback() {
    super.connectedCallback();
    this.packageService.fetchPackages();
  }

  render() {
    const { packages, selectedPackages } = this.store.value;
    const selectedSet = new Set(selectedPackages || []);

    return html`
      <div class="wrap" role="main">
        ${selectedPackages.length > 0 ? html`
          <div class="mb-3 d-flex align-items-center gap-2">
            <span>${selectedPackages.length} selected</span>
            <select class="form-select form-select-sm w-auto" .value=${this.bulkActionValue} @change=${this._onBulkActionChange.bind(this)}>
              <option value="update">Update</option>
              <option value="set-channel">Set channel…</option>
            </select>
            ${this.bulkActionValue === 'set-channel' ? html`
              <select class="form-select form-select-sm w-auto" .value=${this.bulkChannelValue} @change=${this._onBulkChannelChange.bind(this)}>
                ${['stable','beta','develop','alpha'].map(opt => html`<option value="${opt}">${opt}</option>`) }
              </select>
            ` : ''}
            <button class="btn btn-sm btn-primary" @click=${this._applyBulk.bind(this)}>Apply</button>
            <button class="btn btn-sm btn-outline-secondary" @click=${this._clearSelection.bind(this)}>Clear</button>
          </div>
        `: ''}
        <ui-button
          text="${translations.addPackage}"
          variant="primary"
          @click="${() => updateState({ activeModal: 'createPackage' })}"
        ></ui-button>
        ${packages.length === 0
          ? html`<p class="text-muted" role="status">${translations.noPackages(packages.length)}</p>`
          : html`<div class="list-group" @click="${this._handlePackageAction}" @toggle-select=${this._toggleSelect.bind(this)} role="list">
              ${packages.map(
                (pkg) => html`<package-row .pkg=${pkg} .selected=${selectedSet.has(pkg.id ?? pkg.repo)} role="listitem"></package-row>`
              )}
            </div>`}
      </div>
    `;
  }

  _handlePackageAction(e) {
    const row = e.target.closest('package-row');
    if (!row) return;
    if (e.type === 'click' && e.target.matches('ui-button')) {
      const action = e.target.textContent.trim().toLowerCase();
      switch (action) {
        case 'sync':
          this.packageService.syncPackage(row.pkg.id);
          break;
        case 'update':
          this.packageService.updatePackage(row.pkg.id);
          break;
        case 'assign app':
          updateState({ activeModal: 'assignApp', selectedPackageId: row.pkg.id });
          break;
      }
    }
  }

  _toggleSelect(e) {
    const { repo, checked } = e.detail || {};
    if (!repo) return;
    const current = this.store.value.selectedPackages || [];
    const set = new Set(current);
    if (checked) {
      set.add(repo);
    } else {
      set.delete(repo);
    }
    updateState({ selectedPackages: Array.from(set) });
  }

  async _applyBulk() {
    const state = this.store.value;
    const repos = state.selectedPackages || [];
    if (!repos.length) return;
    const action = this.bulkActionValue;
    const channel = this.bulkChannelValue;
    try {
      const result = await this.packageService.bulkAction(repos, action, action === 'set-channel' ? channel : undefined);
      const payload = result?.data ?? result;
      const items = Array.isArray(payload?.results) ? payload.results : [];
      const total = items.length || repos.length;
      const successes = items.length ? items.filter(item => item.ok !== false).length : repos.length;
      const failures = Math.max(0, total - successes);
      if (failures > 0) {
        NotificationService.showError(`${failures} of ${total} operation(s) failed.`);
      }
      if (successes > 0) {
        const successMsg = action === 'set-channel'
          ? `Channel set to ${channel} for ${successes}/${total} package(s).`
          : `Update queued for ${successes}/${total} package(s).`;
        NotificationService.showSuccess(successMsg);
      }
      await this.packageService.fetchPackages();
      this._clearSelection();
    } catch (e) {
      NotificationService.showError(e.message || 'Bulk action failed.');
    }
  }

  _clearSelection() {
    updateState({ selectedPackages: [] });
    this.bulkActionValue = 'update';
    this.bulkChannelValue = 'stable';
  }

  _onBulkActionChange(event) {
    this.bulkActionValue = event.target.value;
  }

  _onBulkChannelChange(event) {
    this.bulkChannelValue = event.target.value;
  }

  createRenderRoot() {
    return this; // Disable shadow DOM
  }
}
