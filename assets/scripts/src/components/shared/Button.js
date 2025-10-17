const { __ } = wp.i18n;
import { LitElement, html, css } from 'lit';
import { customElement, property } from 'lit/decorators.js';

@customElement('ui-button')
export class UIButton extends LitElement {
  @property({ type: String }) text = 'Click Me';
  @property({ type: String }) variant = 'primary';
  @property({ type: Boolean }) disabled = false;
  @property({ type: Boolean }) loading = false;

  createRenderRoot() {
    return this;
  }
  
  render() {
    return html`
      <button
        class="btn btn-${this.variant}"
        ?disabled=${this.disabled || this.loading}
      >
        ${this.text}
        ${this.loading ? html`<ui-spinner class="spinner"></ui-spinner>` : ''}
      </button>
    `;
  }

}
