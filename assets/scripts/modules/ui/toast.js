import Toastify from 'toastify-js';

/**
 * @param {string} text
 * @param {'success'|'error'} [type='success']
 */
export const toast = (text, type = 'success') => {
	Toastify({
		text,
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