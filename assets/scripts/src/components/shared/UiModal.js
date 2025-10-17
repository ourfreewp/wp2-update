import { LitElement, html } from 'lit';
import { customElement, property } from 'lit/decorators.js';

@customElement('ui-modal')
export class UiModal extends LitElement {
  @property({ type: String }) title = '';
  @property({ type: Boolean }) isOpen = false;

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
}