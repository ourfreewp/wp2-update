import { fixture, html, expect } from '@open-wc/testing';
import '../../assets/scripts/src/views/HealthView.js';
import '../../assets/scripts/src/views/PackagesView.js';

describe('View Components', () => {
  it('renders HealthView correctly', async () => {
    const el = await fixture(html`<health-view></health-view>`);

    expect(el).shadowDom.to.equalSnapshot();
  });

  it('renders PackagesView correctly', async () => {
    const el = await fixture(html`<packages-view></packages-view>`);

    expect(el).shadowDom.to.equalSnapshot();
  });
});