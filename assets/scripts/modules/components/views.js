import { app_state } from '../state/store.js';

const { __ } = wp.i18n;

export const render_view = (stage) => {
	console.log('Rendering view for stage:', stage);
	let matched = false;

	document.querySelectorAll('.workflow-step').forEach((el) => {
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