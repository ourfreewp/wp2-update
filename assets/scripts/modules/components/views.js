import { app_state } from '../state/store.js';

const { __ } = wp.i18n;

export const render_view = (stage) => {
	console.log('Rendering view for stage:', stage);
	let matched = false;

	document.querySelectorAll('.wp2-workflow-step').forEach((el) => {
		el.hidden = el.id !== stage;
		if (el.id === stage) matched = true;
		console.log(`Element #${el.id} visibility:`, !el.hidden);
	});

	if (!matched) {
		console.warn(`No matching element found for stage: ${stage}`);
	}

	// Update "Last Synced" if present
	const last = document.getElementById('wp2-last-sync');
	if (last) {
		const s = app_state.get();
		last.textContent = __('Last Synced: ', 'wp2-update') + s.connection.health.lastSync;
		console.log('Updated Last Synced:', last.textContent);
	}
};

export const render_connecting_view = () => {
	const container = document.getElementById('connecting-to-github');
	if (!container) return;

	// If the connecting view already exists, don't re-render it.
	if (container.querySelector('.wp2-large-spinner')) {
		return;
	}

	container.innerHTML = `
        <h2 class="wp2-step-title">${__('2. Connecting to GitHub', 'wp2-update')}</h2>
        <div class="wp2-spinner wp2-large-spinner"></div>
        <p class="wp2-p-description">${__('Please complete the app installation in the new tab that has opened.', 'wp2-update')}</p>
        <div class="wp2-form-footer wp2-flex wp2-justify-center">
            <button class="wp2-button wp2-button-secondary" data-wp2-action="cancel-connection">${__('Cancel', 'wp2-update')}</button>
        </div>
    `;
};