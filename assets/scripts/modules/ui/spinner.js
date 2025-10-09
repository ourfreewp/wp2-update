// Utility functions for managing a global loading spinner

/**
 * Show the global loading spinner.
 */
export const show_global_spinner = () => {
	const id = 'wp2-global-spinner';
	if (document.getElementById(id)) return;
	const el = document.createElement('div');
	el.id = id;
	el.className = 'wp2-global-spinner';
	document.body.appendChild(el);
};

/**
 * Hide the global loading spinner.
 */
export const hide_global_spinner = () => {
	document.getElementById('wp2-global-spinner')?.remove();
};