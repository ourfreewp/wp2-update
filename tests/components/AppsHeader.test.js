import { fixture, assert } from '@open-wc/testing';
import { html } from 'lit';
import '../../assets/scripts/components/app/AppsHeader.js';

describe('<apps-header>', () => {
  it('renders the header with a button', async () => {
    const el = await fixture(html`<apps-header></apps-header>`);
    assert.shadowDom.equal(
      el,
      `
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h1>Applications</h1>
        <button class="btn btn-primary">Add App</button>
      </div>
      `
    );
  });

  it('dispatches an event when the button is clicked', async () => {
    const el = await fixture(html`<apps-header></apps-header>`);
    const button = el.querySelector('button');
    let eventFired = false;

    el.addEventListener('click', () => {
      eventFired = true;
    });

    button.click();
    assert.isTrue(eventFired, 'Click event was fired');
  });
});