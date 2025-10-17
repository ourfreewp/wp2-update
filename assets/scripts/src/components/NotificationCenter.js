import { LitElement, html, css } from 'lit';
import { customElement } from 'lit/decorators.js';
import { store } from '../state/store.js';
import { StoreController } from '@nanostores/lit';
import { removeNotification } from '../actions/notificationActions.js';

@customElement('notification-center')
export class NotificationCenter extends LitElement {
  store = new StoreController(this, store);


    createRenderRoot() {
        return this; // Disable shadow DOM
    }

  static styles = css`
    .notifications-container {
      position: fixed;
      top: 50px;
      right: 20px;
      z-index: 1050;
    }
  `;

  _closeNotification(index) {
    removeNotification(index);
  }

  render() {
    const { notifications } = this.store.value;
    return html`
      <div class="notifications-container">
        ${notifications.map((note, index) => html`
          <div class="alert alert-${note.type} alert-dismissible fade show" role="alert">
            ${note.message}
            <button type="button" class="btn-close" @click=${() => this._closeNotification(index)}></button>
          </div>
        `)}
      </div>
    `;
  }
}