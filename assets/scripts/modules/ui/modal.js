/**
 * @param {string} message
 * @param {() => void} onConfirm
 * @param {() => void} [onCancel]
 */
export const confirm_modal = (message, onConfirm, onCancel) => {
	const modal = document.getElementById('wp2-disconnect-modal');
	if (!modal) return console.error('Modal #wp2-disconnect-modal not found');

	const msg = modal.querySelector('.wp2-modal-message');
	const ok = modal.querySelector('[data-wp2-action="confirm-disconnect"]');
	const cancel = modal.querySelector('[data-wp2-action="cancel-disconnect"]');

	if (msg) msg.textContent = message;

	const close = () => {
		modal.classList.remove('is-visible');
		ok.removeEventListener('click', on_ok);
		cancel.removeEventListener('click', on_cancel);
	};
	const on_ok = () => { close(); onConfirm && onConfirm(); };
	const on_cancel = () => { close(); onCancel && onCancel(); };

	ok.addEventListener('click', on_ok);
	cancel.addEventListener('click', on_cancel);
	modal.classList.add('is-visible');
};