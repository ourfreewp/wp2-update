import { fixture, expect } from '@open-wc/testing';
import { html } from 'lit';
import sinon from 'sinon';
import '../../assets/scripts/src/views/PackagesView.js';
import { updateState } from '../../assets/scripts/src/state/store.js';
import { PackageService } from '../../assets/scripts/src/services/PackageService.js';

describe('<packages-view>', () => {
  let fetchStub;

  beforeEach(() => {
    window.wp2UpdateData = {
      apiRoot: '/wp-json/wp2-update/v1',
      nonce: 'test-nonce',
    };
    fetchStub = sinon.stub(PackageService.prototype, 'fetchPackages').resolves();
    updateState({ packages: [], selectedPackages: [] });
  });

  afterEach(() => {
    fetchStub.restore();
  });

  it('renders call-to-action button and list container', async () => {
    const el = await fixture(html`<packages-view></packages-view>`);
    const addButton = el.querySelector('ui-button');
    expect(addButton).to.exist;
    expect(addButton.getAttribute('text')).to.equal('Add Package');
  });

  it('renders package rows when store has packages', async () => {
    updateState({ packages: [{ id: 'pkg-1', name: 'Test Package', status: 'up_to_date' }] });
    const el = await fixture(html`<packages-view></packages-view>`);
    await el.updateComplete;
    const packageRow = el.querySelector('package-row');
    expect(packageRow).to.exist;
    expect(packageRow.pkg.name).to.equal('Test Package');
  });

  it('shows empty state when packages array is empty', async () => {
    updateState({ packages: [] });
    const el = await fixture(html`<packages-view></packages-view>`);
    await el.updateComplete;
    const emptyState = el.querySelector('.text-muted');
    expect(emptyState).to.exist;
    expect(emptyState.textContent).to.contain('No packages');
  });
});
