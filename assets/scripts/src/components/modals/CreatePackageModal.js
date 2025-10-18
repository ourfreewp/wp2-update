import { LitElement, html } from 'lit';
import { createPackage } from '../../actions/packageActions.js';

class CreatePackageModal extends LitElement {
  render() {
    return html`
      <div class="modal fade" id="createPackageModal" tabindex="-1" aria-labelledby="createPackageModalLabel" aria-hidden="true">
        <div class="modal-dialog">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title" id="createPackageModalLabel">Create New Package</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <form id="create-package-form">
                <div class="mb-3">
                  <label for="packageName" class="form-label">Package Name</label>
                  <input type="text" class="form-control" id="packageName" name="packageName" required />
                </div>
                <div class="mb-3">
                  <label for="packageRepo" class="form-label">Repository</label>
                  <input type="text" class="form-control" id="packageRepo" name="packageRepo" required />
                </div>
              </form>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
              <button type="button" class="btn btn-primary" @click="${this._handleSave}">Save Package</button>
            </div>
          </div>
        </div>
      </div>
    `;
  }

  createRenderRoot() {
    return this; // Disable shadow DOM
  }

  _handleSave() {
    const form = this.querySelector('#create-package-form');
    if (!form.checkValidity()) {
      form.reportValidity();
      return;
    }

    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());

    createPackage(data);
  }

  connectedCallback() {
    super.connectedCallback();
    const modal = this.querySelector('#createPackageModal');

    modal.addEventListener('shown.bs.modal', () => {
      modal.removeAttribute('aria-hidden');
      const firstFocusable = modal.querySelector('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
      if (firstFocusable) firstFocusable.focus();
    });

    modal.addEventListener('hidden.bs.modal', () => {
      modal.setAttribute('aria-hidden', 'true');
      const triggerButton = document.querySelector(`[data-bs-target="#${modal.id}"]`);
      if (triggerButton) triggerButton.focus();
    });
  }
}

if (!customElements.get('create-package-modal')) {
  customElements.define('create-package-modal', CreatePackageModal);
}
