import { Logger } from '../utils.js';

/**
 * @param {string} message
 * @param {() => void} onConfirm
 * @param {() => void} [onCancel]
 */
// Standardize modal structure with consistent header and footer
export const confirm_modal = (message, onConfirm, onCancel) => {
    const modal = document.getElementById('wp2-disconnect-modal');
    if (!modal) {
        Logger.error('Modal #wp2-disconnect-modal not found');
        return;
    }

    const header = modal.querySelector('.wp2-modal-header');
    const msg = modal.querySelector('.wp2-modal-message');
    const footer = modal.querySelector('.wp2-modal-footer');
    const ok = modal.querySelector('[data-wp2-action="confirm-disconnect"]');
    const cancel = modal.querySelector('[data-wp2-action="cancel-disconnect"]');

    if (header) header.textContent = 'Confirmation'; // Add a consistent header
    if (msg) msg.textContent = message;
    if (footer) footer.textContent = 'Please confirm your action.'; // Add a consistent footer

    const close = () => {
        modal.classList.remove('is-visible');
        modal.hidden = true; // Set back to hidden on close
        ok.removeEventListener('click', on_ok);
        cancel.removeEventListener('click', on_cancel);
    };

    const on_ok = () => { close(); onConfirm && onConfirm(); };
    const on_cancel = () => { close(); onCancel && onCancel(); };

    ok.addEventListener('click', on_ok);
    cancel.addEventListener('click', on_cancel);

    modal.hidden = false; // Remove hidden attribute to make it interactive
    modal.classList.add('is-visible');
};

/**
 * Displays a confirmation modal for destructive actions.
 * @param {string} title - The title of the modal.
 * @param {string} message - The message to display in the modal.
 * @param {() => void} onConfirm - Callback for the confirm action.
 * @param {() => void} [onCancel] - Optional callback for the cancel action.
 */
export const showConfirmationModal = (title, message, onConfirm, onCancel) => {
    const modal = document.createElement('div');
    modal.className = 'wp2-modal';
    modal.innerHTML = `
        <div class="wp2-modal-content">
            <header class="wp2-modal-header">${title}</header>
            <div class="wp2-modal-body">${message}</div>
            <footer class="wp2-modal-footer">
                <button class="wp2-btn wp2-btn--ghost" id="wp2-modal-cancel">Cancel</button>
                <button class="wp2-btn wp2-btn--primary" id="wp2-modal-confirm">Confirm</button>
            </footer>
        </div>
    `;

    const closeModal = () => {
        modal.remove();
    };

    modal.querySelector('#wp2-modal-cancel').addEventListener('click', () => {
        closeModal();
        if (onCancel) onCancel();
    });

    modal.querySelector('#wp2-modal-confirm').addEventListener('click', () => {
        closeModal();
        if (onConfirm) onConfirm();
    });

    document.body.appendChild(modal);
};
