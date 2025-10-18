import { LitElement, html, css } from 'lit';
import { customElement, state } from 'lit/decorators.js';
import { store, updateState } from '../state/store.js';
import { StoreController } from '@nanostores/lit';
import { PollingService } from '../services/PollingService.js';

@customElement('logs-view')
export class LogsView extends LitElement {
  store = new StoreController(this, store);
  @state() query = '';
  @state() level = '';
  @state() correlation = '';

  connectedCallback() {
    super.connectedCallback();
    this.startPolling();
  }

  disconnectedCallback() {
    if (this.poller) this.poller.stopPolling();
    super.disconnectedCallback();
  }

  startPolling() {
    if (this.poller) {
      this.poller.stopPolling();
    }
    this.poller = PollingService.startPolling({
      endpoint: this._endpoint(),
      interval: 5000,
      onSuccess: (payload) => {
        const logs = payload?.data?.logs || payload?.logs || [];
        updateState({ logs });
      },
      onError: (e) => {
        console.error('Logs polling failed', e);
      }
    });
  }

  _endpoint() {
    const params = new URLSearchParams();
    if (this.query) params.set('q', this.query);
    if (this.level) params.set('level', this.level);
    if (this.correlation) params.set('correlation_id', this.correlation);
    params.set('limit', '50');
    const qs = params.toString();
    return qs ? `/logs?${qs}` : '/logs';
  }

  _onSearch(e) {
    this.query = e.target.value;
    this.startPolling();
  }

  _onLevel(e) {
    this.level = e.target.value;
    this.startPolling();
  }

  _onCorrelation(e) {
    this.correlation = e.target.value;
    this.startPolling();
  }

  _clearFilters() {
    this.query = '';
    this.level = '';
    this.correlation = '';
    this.startPolling();
  }

  render() {
    const { logs } = this.store.value;
    return html`
      <div class="logs-view">
        <div class="d-flex align-items-center justify-content-between mb-3">
          <h2 class="h4 m-0">Logs</h2>
          <div class="d-flex gap-2">
            <input class="form-control" style="max-width: 220px" placeholder="Search message" .value=${this.query} @input=${this._onSearch.bind(this)} />
            <input class="form-control" style="max-width: 220px" placeholder="Correlation ID" .value=${this.correlation} @input=${this._onCorrelation.bind(this)} />
            <select class="form-select" style="max-width: 160px" .value=${this.level} @change=${this._onLevel.bind(this)}>
              <option value="">All Levels</option>
              <option value="debug">Debug</option>
              <option value="info">Info</option>
              <option value="warning">Warning</option>
              <option value="error">Error</option>
            </select>
            <button class="btn btn-outline-secondary" type="button" @click=${this._clearFilters.bind(this)}>Clear</button>
          </div>
        </div>
        <div class="logs-container">
          ${logs.map(
            (log) => html`
              <div class="log-entry">
                <span class="log-timestamp">${log.timestamp}</span>
                <span class="log-level log-level-${(log.level || 'INFO').toString().toLowerCase()}">${log.level || 'INFO'}</span>
                <span class="log-message">${log.message}</span>
              </div>
            `
          )}
        </div>
      </div>
    `;
  }

  static styles = css`
    .logs-view {
      display: flex;
      flex-direction: column;
      gap: 1rem;
    }

    .logs-container {
      max-height: 400px;
      overflow-y: auto;
      background: #f9f9f9;
      padding: 1rem;
      border: 1px solid #ddd;
      border-radius: 4px;
    }
    .log-entry {
      display: flex;
      gap: 1rem;
      padding: 0.5rem 0;
      border-bottom: 1px solid #eee;
    }
    .log-timestamp {
      color: #888;
      font-size: 0.9rem;
    }
    .log-level {
      font-weight: bold;
      text-transform: uppercase;
    }
    .log-level-info {
      color: #007bff;
    }
    .log-level-warn {
      color: #ffc107;
    }
    .log-level-error {
      color: #dc3545;
    }
    .log-message {
      flex: 1;
    }
  `;
}
