import { LitElement, html } from 'lit';
import { customElement } from 'lit/decorators.js';
import { store, updateState } from '../state/store.js';
import { StoreController } from '@nanostores/lit';
import { Modal } from 'bootstrap';

// Import all your modal components
import '@modals/CreatePackageModal.js';
import '@modals/AppDetailsModal.js';
import '@modals/RestoreBackupModal.js';
import '@modals/RollbackModal.js';
import '@modals/AssignAppModal.js';

@customElement('modal-manager')
export class ModalManager extends LitElement {
  store = new StoreController(this, store);
  modalInstance = null;


    createRenderRoot() {
        return this; // Disable shadow DOM
    }

  updated(changedProperties) {
    super.updated(changedProperties);
    const { activeModal } = this.store.value;
    const modalElement = this.querySelector('.modal'); // Updated to query the light DOM

    if (modalElement) {
      this.modalInstance = Modal.getOrCreateInstance(modalElement);
      if (activeModal) {
        this.modalInstance.show();
      }
      modalElement.addEventListener('hidden.bs.modal', this._handleModalClose, { once: true });
    } else if (this.modalInstance) {
      this.modalInstance.dispose();
      this.modalInstance = null;
    }
  }

  _handleModalClose = () => {
    updateState({ activeModal: null, modalProps: {} });
  };

  render() {
    const { activeModal, modalProps } = this.store.value;

    switch (activeModal) {
      case 'createPackage':
        return html`<create-package-modal .props=${modalProps}></create-package-modal>`;
      case 'appDetails':
        return html`<app-details-modal appId=${modalProps.appId}></app-details-modal>`;
      case 'restoreBackup':
        return html`<restore-backup-modal .props=${modalProps}></restore-backup-modal>`;
      case 'rollback':
        return html`<rollback-modal .props=${modalProps}></rollback-modal>`;
      case 'assignApp':
        return html`<assign-app-modal .props=${modalProps}></assign-app-modal>`;
      default:
        return html``;
    }
  }

  disconnectedCallback() {
    super.disconnectedCallback();
    const modalElement = this.querySelector('.modal'); // Updated to query the light DOM
    if (modalElement) {
      modalElement.removeEventListener('hidden.bs.modal', this._handleModalClose);
    }
    if (this.modalInstance) {
      this.modalInstance.dispose();
    }
  }
}
