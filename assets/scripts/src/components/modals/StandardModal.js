import { LitElement, html, css } from 'lit';

class StandardModal extends LitElement {
    static properties = {
        title: { type: String },
        bodyContent: { type: String },
        footerActions: { type: Array },
        modalId: { type: String }
    };

    static styles = css`
        /* Add your modal styles here */
    `;

    render() {
        return html`
            <div class="wp2-modal" role="dialog" aria-modal="true" aria-labelledby="${this.modalId}-title">
                <div class="wp2-modal-header">
                    <h2 id="${this.modalId}-title">${this.title}</h2>
                </div>
                <div class="wp2-modal-body">
                    ${this.bodyContent}
                </div>
                <div class="wp2-modal-footer d-flex justify-content-between">
                    ${this.footerActions.map(action => html`
                        <button type="button" class="wp2-btn ${action.class}" ...attributes="${action.attributes}">
                            ${action.label}
                        </button>
                    `)}
                </div>
            </div>
        `;
    }


    createRenderRoot() {
        return this; // Disable shadow DOM
    }
}

customElements.define('standard-modal', StandardModal);
