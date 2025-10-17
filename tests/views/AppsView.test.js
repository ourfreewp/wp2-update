import { fixture, assert } from '@open-wc/testing';
import { html } from 'lit';
import '../../assets/scripts/views/AppsView.js';

describe('<apps-view>', () => {
  it('renders the apps view with header and list', async () => {
    const el = await fixture(html`<apps-view></apps-view>`);
    assert.shadowDom.include(el, '<apps-header></apps-header>');
    assert.shadowDom.include(el, '<div class="list-group"></div>');
  });

  it('renders app rows dynamically', async () => {
    const el = await fixture(html`<apps-view></apps-view>`);
    el.store.value = { apps: [{ id: 1, name: 'Test App' }] };
    await el.updateComplete;

    const appRow = el.querySelector('app-row');
    assert.exists(appRow, 'App row is rendered');
    assert.equal(appRow.app.name, 'Test App', 'App row has correct data');
  });
});