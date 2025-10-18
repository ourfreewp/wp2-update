import { LitElement, html, css } from 'lit';
import { customElement, property } from 'lit/decorators.js';

@customElement('ui-notification')
export class UiNotification extends LitElement {
  @property({ type: String }) message = '';
  @property({ type: String }) type = 'info';

  static styles = css`
    .notification {
      padding: 1rem;
      border-radius: 4px;
      margin-bottom: 1rem;
      font-size: 1rem;
    }
    .info { background: #e7f3fe; color: #31708f; }
    .success { background: #dff0d8; color: #3c763d; }
    .error { background: #f2dede; color: #a94442; }
    .warning { background: #fcf8e3; color: #8a6d3b; }
  `;

  render() {
    return html`
      <div class="notification ${this.type}" role="alert" aria-live="assertive">
        ${this.message}
      </div>
    `;
  }
}
