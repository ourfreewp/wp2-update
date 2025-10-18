import { LitElement, html } from 'lit';
import { customElement, state } from 'lit/decorators.js';
import { StoreController } from '@nanostores/lit';
import { store } from '../state/store.js';
import { fetchHealthData, refreshHealthData } from '../actions/healthActions.js';
import { NotificationService } from '../services/NotificationService.js';
import '../components/shared/UiButton.js';

@customElement('health-view')
export class HealthView extends LitElement {
  store = new StoreController(this, store);
  @state() loading = false;

  connectedCallback() {
    super.connectedCallback();
    if (!this.store.value.health?.groups) {
      fetchHealthData().catch((error) => NotificationService.showError(error?.message || 'Failed to load health data.'));
    }
  }

  render() {
    const groups = this.store.value.health?.groups || [];
    return html`
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">System Health</h1>
        <ui-button
          text=${this.loading ? 'Refreshing…' : 'Refresh'}
          variant="outline-secondary"
          ?disabled=${this.loading}
          @click=${this.refreshHealth}
        ></ui-button>
      </div>
      ${groups.length ? this.renderGroups(groups) : this.renderEmpty()}
    `;
  }

  renderGroups(groups) {
    return html`
      <div class="row g-4">
        ${groups.map((group) => html`
          <div class="col-12">
            <div class="card shadow-sm">
              <div class="card-header bg-light">
                <h2 class="h6 mb-0">${group.title}</h2>
              </div>
              <div class="list-group list-group-flush">
                ${(group.checks || []).map((check) => this.renderCheck(check))}
              </div>
            </div>
          </div>
        `)}
      </div>
    `;
  }

  renderCheck(check) {
    if (check?.data && Array.isArray(check.data)) {
      return html`
        <div class="list-group-item">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <strong>${check.title || 'REST Routes'}</strong>
              <p class="text-muted small mb-2">${check.message || ''}</p>
            </div>
            <span class="badge bg-secondary-subtle text-secondary">${check.data.length} routes</span>
          </div>
          <pre class="small bg-light border rounded p-2 mb-0">${check.data.map((route) => `${route.route} [${route.methods}]`).join('\n')}</pre>
        </div>
      `;
    }

    const status = (check?.status || 'unknown').toLowerCase();
    const variants = {
      pass: 'bg-success-subtle text-success',
      warn: 'bg-warning-subtle text-warning',
      warning: 'bg-warning-subtle text-warning',
      error: 'bg-danger-subtle text-danger',
      fail: 'bg-danger-subtle text-danger',
    };
    const badgeClass = variants[status] || 'bg-secondary-subtle text-secondary';
    return html`
      <div class="list-group-item d-flex justify-content-between align-items-start">
        <div>
          <strong>${check?.label || check?.title || 'Check'}</strong>
          ${check?.message ? html`<p class="text-muted small mb-0">${check.message}</p>` : ''}
        </div>
        <span class="badge ${badgeClass}">${status}</span>
      </div>
    `;
  }

  renderEmpty() {
    return html`
      <div class="alert alert-info" role="status">
        Health data unavailable. Try refreshing.
      </div>
    `;
  }

  async refreshHealth() {
    this.loading = true;
    try {
      await refreshHealthData();
      NotificationService.showSuccess('Health checks refreshed.');
    } catch (e) {
      NotificationService.showError(e?.message || 'Failed to refresh health data.');
    } finally {
      this.loading = false;
    }
  }

  createRenderRoot() {
    return this; // Disable shadow DOM
  }
}
