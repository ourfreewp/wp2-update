import { app_state } from '../state/store.js';

export const render_view = (stage) => {
	document.querySelectorAll('.workflow-step').forEach((el) => {
		el.hidden = el.id !== stage;
	});
	// Update "Last Synced" if present
	const last = document.getElementById('wp2-last-sync');
	if (last) {
		const s = app_state.get();
		last.textContent = __('Last Synced: ', 'wp2-update') + s.connection.health.lastSync;
	}
};