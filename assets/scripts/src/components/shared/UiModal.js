import { LitElement, html } from 'lit';
import { customElement, property } from 'lit/decorators.js';
import { Modal } from 'bootstrap';
import { focusTrap } from '../../directives/focusTrap.js';

@customElement('ui-modal')
export class UiModal extends LitElement {
  @property({ type: String }) title = '';
  @property({ type: Boolean, reflect: true }) isOpen = false;

  modalInstance = null;

  render() {
    if (!this.isOpen) return html``;

    return html`
      <div class="modal" style="display: block;">
        <div class="modal-dialog">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">${this.title}</h5>
              <button type="button" class="btn-close" @click=${this._close}></button>
            </div>
            <div class="modal-body">
              <slot name="body"></slot>
            </div>
            <div class="modal-footer">
              <slot name="footer"></slot>
            </div>
          </div>
        </div>
      </div>
    `;
  }

  _close() {
    this.dispatchEvent(new CustomEvent('close', { bubbles: true, composed: true }));
  }

  createRenderRoot() {
    return this;
  }

  updated(changedProperties) {
    if (this.isOpen) {
      if (!this._trapCleanup) {
        this._trapCleanup = focusTrap(this.querySelector('.modal'));
      }
    } else {
      if (this._trapCleanup) {
        this._trapCleanup();
        this._trapCleanup = null;
      }
    }
  }

  firstUpdated() {
    const modalElement = this.renderRoot.querySelector('.modal');
    modalElement.addEventListener('hidden.bs.modal', this._handleClose);
  }

  disconnectedCallback() {
    super.disconnectedCallback();
    const modalElement = this.renderRoot.querySelector('.modal');
    modalElement.removeEventListener('hidden.bs.modal', this._handleClose);
    this.modalInstance?.dispose();
  }

  _getModalInstance() {
    if (!this.modalInstance) {
      const modalElement = this.renderRoot.querySelector('.modal');
      this.modalInstance = new Modal(modalElement);
    }
    return this.modalInstance;
  }

  _handleClose = () => {
    this.isOpen = false;
    this.dispatchEvent(new CustomEvent('close', { bubbles: true, composed: true }));
  };
}