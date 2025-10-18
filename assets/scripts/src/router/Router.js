import { router } from 'lit-element-router';
import { LitElement, html } from 'lit';
import { customElement } from 'lit/decorators.js';

@customElement('wp2-router')
export class Wp2Router extends router(LitElement) {
  static get routes() {
    return [
      { name: 'dashboard', pattern: '/' },
      { name: 'health', pattern: '/health' },
      { name: 'packages', pattern: '/packages' },
      { name: 'apps', pattern: '/apps' },
      { name: 'logs', pattern: '/logs' },
      { name: 'backups', pattern: '/backups' },
      { name: 'config', pattern: '/config' },
    ];
  }

  constructor() {
    super();
    this.route = 'dashboard';
    this._views = {};
  }

  router(route, params, query, data) {
    this.route = route;
    this.requestUpdate();
  }

  async _getView(name) {
    if (!this._views[name]) {
      switch (name) {
        case 'dashboard':
          await import('../views/DashboardView.js');
          break;
        case 'health':
          await import('../views/HealthView.js');
          break;
        case 'packages':
          await import('../views/PackagesView.js');
          break;
        case 'apps':
          await import('../views/AppsView.js');
          break;
        case 'logs':
          await import('../views/LogsView.js');
          break;
        case 'backups':
          await import('../views/BackupsView.js');
          break;
        case 'config':
          await import('../views/ConfigView.js');
          break;
      }
      this._views[name] = true;
    }
  }

  updated(changedProps) {
    if (changedProps.has('route')) {
      this._getView(this.route);
    }
  }

  render() {
    return html`
      <div>
        ${this.route === 'dashboard'
          ? html`<dashboard-view></dashboard-view>`
          : this.route === 'health'
          ? html`<health-view></health-view>`
          : this.route === 'packages'
          ? html`<packages-view></packages-view>`
          : this.route === 'apps'
          ? html`<apps-view></apps-view>`
          : this.route === 'backups'
          ? (window.wp2UpdateData?.caps?.restoreBackups ? html`<backups-view></backups-view>` : html`<div class="alert alert-warning">You do not have permission to view Backups.</div>`)
          : this.route === 'config'
          ? (window.wp2UpdateData?.caps?.manage ? html`<config-view></config-view>` : html`<div class="alert alert-warning">You do not have permission to view Config.</div>`)
          : (window.wp2UpdateData?.caps?.viewLogs ? html`<logs-view></logs-view>` : html`<div class="alert alert-warning">You do not have permission to view Logs.</div>`)}
      </div>
    `;
  }
}
