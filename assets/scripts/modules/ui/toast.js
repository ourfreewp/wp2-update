import Toastify from 'toastify-js';

/**
 * Enhanced toast function to include optional detailed error messages.
 * @param {string} text - The main message to display.
 * @param {'success'|'error'} [type='success'] - The type of toast (success or error).
 * @param {string} [details] - Optional detailed message for errors.
 */
export const toast = (text, type = 'success', details) => {
	const message = details && type === 'error' ? `${text}\nDetails: ${details}` : text;
	Toastify({
		text: message,
		duration: 4000,
		gravity: 'bottom',
		position: 'right',
		stopOnFocus: true,
		style: {
			background: type === 'success' ? 'var(--wp2-color-success)' : 'var(--wp2-color-error)',
			borderRadius: 'var(--wp2-border-radius)',
			boxShadow: '0 3px 6px -1px rgba(0,0,0,.12), 0 10px 36px -4px rgba(0,0,0,.3)',
		},
	}).showToast();
};