const { __ } = wp.i18n;
import { LitElement, html, css } from 'lit';
import { customElement, property } from 'lit/decorators.js';

@customElement('ui-button')
export class UIButton extends LitElement {
  @property({ type: String }) text = 'Click Me';
  @property({ type: String }) variant = 'primary';
  @property({ type: Boolean }) disabled = false;
  @property({ type: Boolean }) loading = false;

  static styles = css`
    .btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      position: relative;
    }

    .spinner {
      margin-left: 8px;
    }
  `;

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

  createRenderRoot() {
    return this;
  }
}
