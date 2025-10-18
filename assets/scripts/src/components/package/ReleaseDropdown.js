import { LitElement, html, css } from 'lit';
import { customElement, property, state } from 'lit/decorators.js';
import { fetchReleases, updatePackage } from '../../actions/packageActions.js';

@customElement('release-dropdown')
export class ReleaseDropdown extends LitElement {
  @property({ type: Object }) pkg;
  @state() _releases = [];
  @state() _isLoading = false;

  static styles = css`
    .release-dropdown {
      position: relative;
      display: inline-block;
    }
  `;

  async firstUpdated() {
    this._isLoading = true;
    try {
      this._releases = await fetchReleases(this.pkg.repo);
    } finally {
      this._isLoading = false;
    }
  }

  async _onUpdate(releaseTag) {
    if (confirm(`Are you sure you want to update ${this.pkg.name} to ${releaseTag}?`)) {
      await updatePackage(this.pkg.id, releaseTag);
    }
  }

  render() {
    if (this._isLoading) {
      return html`<div>Loading releases...</div>`;
    }

    return html`
      <div class="dropdown release-dropdown">
        <button class="btn btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
          Update to...
        </button>
        <ul class="dropdown-menu">
          ${this._releases.map(
            (release) => html`
              <li>
                <a class="dropdown-item" href="#" @click=${() => this._onUpdate(release.tag_name)}>
                  ${release.name} (${release.tag_name})
                </a>
              </li>
            `
          )}
        </ul>
      </div>
    `;
  }

  createRenderRoot() {
    return this; // Disable shadow DOM
  }
}
