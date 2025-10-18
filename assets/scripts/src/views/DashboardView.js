import { LitElement, html, css } from 'lit';
import { customElement } from 'lit/decorators.js';
import { StoreController } from '@nanostores/lit';
import { store } from '../state/store.js';
import { fetchPackages } from '../actions/packageActions.js';
import { fetchHealthData } from '../actions/healthActions.js';
import { NotificationService } from '../services/NotificationService.js';

@customElement('dashboard-view')
export class DashboardView extends LitElement {
  store = new StoreController(this, store);

  static styles = css`
    .metric-card {
      min-height: 8rem;
    }
    .list-scroll {
      max-height: 260px;
      overflow-y: auto;
    }
  `;

  createRenderRoot() {
    return this;
  }

  get packages() {
    return this.store.value.packages || [];
  }

  get logs() {
    return (this.store.value.logs || []).slice(0, 8);
  }

  get health() {
    return this.store.value.health?.groups || [];
  }

  get flags() {
    return this.store.value.flags || {};
  }

  get updatesAvailable() {
    return this.packages.filter(pkg => (pkg.status || '').toLowerCase() === 'update_available').length;
  }

  get unlinked() {
    return this.packages.filter(pkg => (pkg.status || '').toLowerCase() === 'unconnected' || !pkg.repo).length;
  }

  get channels() {
    return this.packages.reduce((acc, pkg) => {
      const channel = (pkg.channel || 'stable').toLowerCase();
      acc[channel] = (acc[channel] || 0) + 1;
      return acc;
    }, {});
  }

  async refreshSummary() {
    try {
      await Promise.all([fetchPackages(), fetchHealthData()]);
      NotificationService.showSuccess('Dashboard data refreshed.');
    } catch (e) {
      NotificationService.showError(e?.message || 'Failed to refresh dashboard.');
    }
  }

  renderMetricCards() {
    const packagesCount = this.packages.length;
    const updates = this.updatesAvailable;
    const unlinked = this.unlinked;
    const devMode = this.flags.devMode;

    return html`
      <div class="row g-4 mb-4">
        <div class="col-md-6 col-lg-3">
          <div class="card shadow-sm metric-card">
            <div class="card-body">
              <h2 class="h6 text-muted">Updates Available</h2>
              <p class="display-5 fw-bold text-primary mb-0">${updates}</p>
              <p class="text-muted small mb-0">Out of ${packagesCount} managed packages</p>
            </div>
          </div>
        </div>
        <div class="col-md-6 col-lg-3">
          <div class="card shadow-sm metric-card">
            <div class="card-body">
              <h2 class="h6 text-muted">Unlinked Packages</h2>
              <p class="display-5 fw-bold ${unlinked ? 'text-warning' : 'text-success'} mb-0">${unlinked}</p>
              <p class="text-muted small mb-0">Require GitHub App assignment</p>
            </div>
          </div>
        </div>
        <div class="col-md-6 col-lg-3">
          <div class="card shadow-sm metric-card">
            <div class="card-body">
              <h2 class="h6 text-muted">Release Channels</h2>
              ${Object.keys(this.channels).length
                ? html`<ul class="list-unstyled small mb-0">
                    ${Object.entries(this.channels).map(([channel, count]) => html`<li><span class="badge bg-secondary-subtle text-secondary me-2">${channel}</span>${count}</li>`)}
                  </ul>`
                : html`<p class="text-muted small mb-0">No channel assignments yet.</p>`}
            </div>
          </div>
        </div>
        <div class="col-md-6 col-lg-3">
          <div class="card shadow-sm metric-card ${devMode ? 'border-warning' : ''}">
            <div class="card-body">
              <h2 class="h6 text-muted">Developer Mode</h2>
              <p class="display-6 fw-bold ${devMode ? 'text-warning' : 'text-success'} mb-0">${devMode ? 'Enabled' : 'Disabled'}</p>
              <p class="text-muted small mb-0">Remote updates ${devMode ? 'are currently bypassed' : 'operate normally'}.</p>
            </div>
          </div>
        </div>
      </div>
    `;
  }

  renderLogsPanel() {
    if (!this.logs.length) {
      return html`<p class="text-muted small">No logs available yet.</p>`;
    }
    return html`
      <ul class="list-unstyled mb-0 list-scroll">
        ${this.logs.map((log) => html`
          <li class="border-bottom py-2 small d-flex gap-3">
            <span class="text-muted text-nowrap">${log.timestamp}</span>
            <span class="badge bg-secondary-subtle text-secondary text-uppercase">${log.level || 'INFO'}</span>
            <span>${log.message}</span>
          </li>
        `)}
      </ul>
    `;
  }

  renderPackagesList() {
    const items = this.packages.slice(0, 6);
    if (!items.length) {
      return html`<p class="text-muted small">No managed packages detected. Use “Create Package” to get started.</p>`;
    }
    return html`
      <div class="list-scroll">
        <table class="table table-sm align-middle">
          <thead class="table-light">
            <tr>
              <th>Name</th>
              <th>Status</th>
              <th>Installed</th>
              <th>Latest</th>
            </tr>
          </thead>
          <tbody>
            ${items.map(pkg => html`
              <tr>
                <td>${pkg.name || pkg.slug || pkg.id}</td>
                <td>
                  <span class="badge bg-${(pkg.status || '').toLowerCase() === 'update_available' ? 'warning text-dark' : 'success'}">
                    ${(pkg.status || 'unknown').replace('_', ' ')}
                  </span>
                </td>
                <td>${pkg.version || '—'}</td>
                <td>${pkg.latest || '—'}</td>
              </tr>
            `)}
          </tbody>
        </table>
      </div>
    `;
  }

  render() {
    return html`
      <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h4 mb-0">Dashboard</h1>
        <button class="btn btn-outline-secondary" @click=${this.refreshSummary}>
          Refresh Summary
        </button>
      </div>
      ${this.renderMetricCards()}
      <div class="row g-4">
        <div class="col-lg-6">
          <div class="card shadow-sm h-100">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
              <h2 class="h6 mb-0">Recent Logs</h2>
              <a class="small" href="#/logs">View all</a>
            </div>
            <div class="card-body p-0">
              ${this.renderLogsPanel()}
            </div>
          </div>
        </div>
        <div class="col-lg-6">
          <div class="card shadow-sm h-100">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
              <h2 class="h6 mb-0">Managed Packages</h2>
              <a class="small" href="#/packages">Manage</a>
            </div>
            <div class="card-body p-0">
              ${this.renderPackagesList()}
            </div>
          </div>
        </div>
      </div>
    `;
  }
}
