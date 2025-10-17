import { fixture, html, expect } from '@open-wc/testing';
import '../../assets/scripts/src/components/modals/RollbackModal.js';
import '../../assets/scripts/src/components/modals/AssignAppModal.js';

describe('Modal Components', () => {
  it('renders RollbackModal correctly', async () => {
    const pkg = { name: 'Test Package', repo: 'test/repo', releases: [{ version: '1.0.0' }] };
    const el = await fixture(html`<rollback-modal .pkg=${pkg}></rollback-modal>`);

    expect(el).shadowDom.to.equalSnapshot();
  });

  it('renders AssignAppModal correctly', async () => {
    const pkg = { name: 'Test Package', repo: 'test/repo' };
    const apps = [{ id: '1', name: 'App 1' }, { id: '2', name: 'App 2' }];
    const el = await fixture(html`<assign-app-modal .pkg=${pkg}></assign-app-modal>`);

    // Mock the store to provide apps
    el.store.set({ apps });

    expect(el).shadowDom.to.equalSnapshot();
  });
});