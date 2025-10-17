import { LitElement, html, css } from 'lit';
import { fetchReleaseNotes } from '../../actions/packageActions.js';
import { escapeHtml } from '../../utils/string.js';

class PackageDetailsModal extends LitElement {
    static properties = {
        pkg: { type: Object },
        releaseNotes: { type: Array },
        loadingNotes: { type: Boolean },
    };

    constructor() {
        super();
        this.releaseNotes = [];
        this.loadingNotes = false;
    }

    static styles = css`
        /* Add your modal styles here */
    `;


    createRenderRoot() {
        return this; // Disable shadow DOM
    }

    render() {
        return html`
            <div class="wp2-modal" role="dialog" aria-modal="true">
                <div class="wp2-modal-header">
                    <h2>${escapeHtml(this.pkg.name)}</h2>
                </div>
                <div class="wp2-modal-body">
                    <ul class="nav nav-tabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" @click="${() => this._showTab('details')}" type="button" role="tab">
                                Details
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" @click="${() => this._showTab('release-notes')}" type="button" role="tab">
                                Release Notes
                            </button>
                        </li>
                    </ul>
                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="details-tab" role="tabpanel">
                            <dl class="wp2-detail-grid">
                                <dt>Repository</dt><dd>${escapeHtml(this.pkg.repo)}</dd>
                                <dt>Installed Version</dt><dd>${escapeHtml(this.pkg.version || 'N/A')}</dd>
                                <dt>Latest Version</dt><dd>${escapeHtml(this.pkg.latest || 'N/A')}</dd>
                            </dl>
                        </div>
                        <div class="tab-pane fade" id="release-notes-tab" role="tabpanel">
                            ${this.loadingNotes
                                ? html`<p>Loading release notes...</p>`
                                : this.releaseNotes.length
                                    ? html`<ul>${this.releaseNotes.map(note => html`<li>${escapeHtml(note)}</li>`)}</ul>`
                                    : html`<p>No release notes available.</p>`}
                        </div>
                    </div>
                </div>
                <div class="wp2-modal-footer">
                    <button class="wp2-btn wp2-btn--secondary" @click="${this._closeModal}">Close</button>
                </div>
            </div>
        `;
    }

    _showTab(tab) {
        if (tab === 'release-notes') {
            this._loadReleaseNotes();
        }
    }

    _loadReleaseNotes() {
        this.loadingNotes = true;
        fetchReleaseNotes(this.pkg.repo)
            .then((notes) => {
                this.releaseNotes = notes;
            })
            .catch(() => {
                this.releaseNotes = [];
            })
            .finally(() => {
                this.loadingNotes = false;
            });
    }

    _closeModal() {
        const event = new CustomEvent('close-modal', { bubbles: true, composed: true });
        this.dispatchEvent(event);
    }
}

customElements.define('package-details-modal', PackageDetailsModal);
