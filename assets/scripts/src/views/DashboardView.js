import { LitElement, html, css } from 'lit';

class DashboardView extends LitElement {
    static styles = css`
        /* Add your styles here */
    `;

    createRenderRoot() {
        return this; // Disable shadow DOM
    }

    render() {
        return html`
            <div>
                <button class="btn btn-primary" @click="this.refreshWidgets" data-wp2-action="refresh-widgets">
                    Refresh Widgets
                </button>
            </div>
        `;
    }

    refreshWidgets() {
        console.log('Refresh widgets interaction triggered.');

        // Trigger a custom event for refreshing widgets
        const refreshEvent = new CustomEvent('wp2RefreshWidgets');
        document.dispatchEvent(refreshEvent);
    }
}

customElements.define('dashboard-view', DashboardView);