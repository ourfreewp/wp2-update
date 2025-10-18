import { LitElement, html } from 'lit';
import { customElement, property } from 'lit/decorators.js';
import { packageService } from '../../services/PackageService.js';
import '@components/shared/UiButton.js';

@customElement('package-row')
export class PackageRow extends LitElement {
  @property({ type: Object }) pkg;
  @property({ type: Boolean }) selected = false;

  render() {
    const name = this.pkg?.name || this.pkg?.slug || this.pkg?.id;
    const version = this.pkg?.version || '—';
    const latest = this.pkg?.latest || '—';
    const channel = (this.pkg?.channel || 'stable').toLowerCase();
    const { label: statusLabel, className: statusClass } = this._statusBadge(this.pkg?.status, version, latest);

    return html`
      <div class="package-row">
        <input type="checkbox" class="form-check-input me-2" .checked=${this.selected} @change=${this._onToggle} />
        <div class="package-row__info me-3">
          <div class="package-row__title">
            <strong>${name}</strong>
            <span class="badge ${statusClass} ms-2">${statusLabel}</span>
            <span class="badge bg-secondary ms-2">${channel}</span>
          </div>
          <div class="package-row__meta text-muted small">
            <span>Installed: ${version}</span>
            <span class="ms-2">Latest: ${latest}</span>
          </div>
        </div>
        <ui-button
          text="Sync"
          variant="primary"
          size="sm"
          @click="${this._syncPackage}"
          ?disabled="${this.pkg.isUpdating}"
        ></ui-button>
        <ui-button
          text="Update"
          variant="success"
          size="sm"
          @click="${this._updatePackage}"
          ?disabled="${this.pkg.isUpdating}"
        ></ui-button>
        <ui-button
          text="Assign App"
          variant="warning"
          size="sm"
          @click="${this._openAssignAppModal}"
        ></ui-button>
        <select class="form-select form-select-sm d-inline-block w-auto ms-2" @change=${this._onChannelChange}>
          ${['stable','beta','develop','alpha'].map(opt => html`
            <option value="${opt}" ?selected=${(this.pkg.channel || 'stable') === opt}>${opt}</option>
          `)}
        </select>
      </div>
    `;
  }

  _syncPackage() {
    this.dispatchEvent(new CustomEvent('sync-package', {
      detail: { packageId: this.pkg.id },
      bubbles: true,
      composed: true,
    }));
  }

  async _onChannelChange(e) {
    const channel = e.target.value;
    const repo = this.pkg?.id || this.pkg?.repo;
    if (!repo) return;
    try {
      await packageService.updateReleaseChannel(repo, channel);
    } catch (err) {
      console.error('Failed to update channel', err);
    }
  }

  _onToggle(e) {
    const checked = e.target.checked;
    const repo = this.pkg?.id || this.pkg?.repo;
    this.dispatchEvent(new CustomEvent('toggle-select', { detail: { repo, checked }, bubbles: true, composed: true }));
  }

  _statusBadge(status, currentVersion, latestVersion) {
    const normalized = (status || '').toLowerCase();
    if (normalized === 'update_available') {
      return { label: `Update available (${latestVersion || 'new'})`, className: 'bg-warning text-dark' };
    }
    if (normalized === 'up_to_date') {
      return { label: 'Up to date', className: 'bg-success' };
    }
    if (normalized === 'unconnected') {
      return { label: 'Unconnected', className: 'bg-secondary' };
    }
    return { label: status ? status : 'Unknown', className: 'bg-light text-dark border' };
  }

  _updatePackage() {
    this.dispatchEvent(new CustomEvent('update-package', {
      detail: { packageId: this.pkg.id },
      bubbles: true,
      composed: true,
    }));
  }

  _openAssignAppModal() {
    this.dispatchEvent(new CustomEvent('open-assign-app-modal', {
      detail: { packageId: this.pkg.id },
      bubbles: true,
      composed: true,
    }));
  }

  createRenderRoot() {
    return this;
  }
}
