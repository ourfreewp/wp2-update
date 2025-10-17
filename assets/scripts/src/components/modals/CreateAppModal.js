import { LitElement, html } from 'lit';
import { customElement } from 'lit/decorators.js';
import { createApp } from '../../actions/appActions.js';

/**
 * Creates a new app modal component.
 */
@customElement('create-app-modal')
export class CreateAppModal extends LitElement {
  /**
   * Handles the form submission for creating a new app.
   * @param {Event} event - The submit event.
   */
  async _handleSubmit(event) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    const appName = formData.get('appName');

    if (!appName) {
      alert('App name is required.');
      return;
    }

    // Use the createApp action instead of direct service calls.
    await createApp({ name: appName });
  }

  /**
   * Renders the modal template.
   * @returns {TemplateResult} The template result.
   */
  render() {
    return html`
      <div class="modal fade" id="createAppModal" tabindex="-1">
        <div class="modal-dialog">
          <div class="modal-content">
            <form @submit=${this._handleSubmit}>
              <div class="modal-header">
                <h5 class="modal-title">Create New App</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body">
                <div class="mb-3">
                  <label for="appName" class="form-label">App Name</label>
                  <input type="text" class="form-control" id="appName" name="appName" required>
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="submit" class="btn btn-primary">Create App</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    `;
  }


    createRenderRoot() {
        return this; // Disable shadow DOM
    }
}
