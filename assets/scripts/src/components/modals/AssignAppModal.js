import { LitElement, html } from 'lit';
import { customElement, property } from 'lit/decorators.js';
import { assignAppToPackage } from '../../actions/packageActions.js';
import { store } from '../../state/store.js';

@customElement('assign-app-modal')
export class AssignAppModal extends LitElement {
  @property({ type: Object }) pkg;


    createRenderRoot() {
        return this; // Disable shadow DOM
    }

  render() {
    const { pkg } = this;
    const { apps } = store.get();

    if (!pkg || !apps) return html``;

    return html`
      <div class="modal fade" id="assignAppModal" tabindex="-1">
        <div class="modal-dialog">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">Assign App to ${pkg.name}</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <form @submit=${this._handleSubmit} data-repo="${pkg.repo}">
                <p>Select a GitHub App:</p>
                <select name="app_id" class="form-select">
                  ${apps.map(app => html`<option value="${app.id}">${app.name}</option>`)}</select>
              </form>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-primary">Assign App</button>
            </div>
          </div>
        </div>
      </div>
    `;
  }

  async _handleSubmit(event) {
    event.preventDefault();
    const form = event.target;
    const repo = form.getAttribute('data-repo');
    const appId = form.querySelector('select').value;

    await assignAppToPackage(repo, appId);
  }

  createRenderRoot() {
    return this; // To use Bootstrap styling without shadow DOM
  }
}
