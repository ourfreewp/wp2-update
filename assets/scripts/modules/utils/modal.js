import { updateState } from '../state/store.js';

const MODAL_OPEN_CLASS = 'wp2-modal-open';

export const modalManager = {
    /**
     * Opens a modal with the given content.
     * @param {string} content - The HTML content for the modal.
     */
    open(content) {
        updateState({ modal: { isOpen: true, content } });
        document.body.classList.add(MODAL_OPEN_CLASS);

        // Add ARIA attributes for accessibility
        const modalContainer = document.querySelector('.wp2-modal-container');
        if (modalContainer) {
            modalContainer.setAttribute('role', 'dialog');
            modalContainer.setAttribute('aria-modal', 'true');
            modalContainer.setAttribute('aria-labelledby', 'wp2-modal-title');
        }

        // Add a listener to close the modal when clicking the overlay
        setTimeout(() => {
            const overlay = document.querySelector('.wp2-modal-overlay');
            overlay?.addEventListener('click', (e) => {
                if (e.target === overlay) {
                    this.close();
                }
            });
        }, 0);

        // Implement focus trapping
        const focusableElements = modalContainer?.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
        if (focusableElements && focusableElements.length > 0) {
            const firstElement = focusableElements[0];
            const lastElement = focusableElements[focusableElements.length - 1];

            modalContainer?.addEventListener('keydown', (e) => {
                if (e.key === 'Tab') {
                    if (e.shiftKey) {
                        if (document.activeElement === firstElement) {
                            e.preventDefault();
                            lastElement.focus();
                        }
                    } else {
                        if (document.activeElement === lastElement) {
                            e.preventDefault();
                            firstElement.focus();
                        }
                    }
                }
            });
        }
    },

    /**
     * Closes the currently open modal.
     */
    close() {
        updateState({ modal: { isOpen: false, content: null } });
        document.body.classList.remove(MODAL_OPEN_CLASS);
    },
};
