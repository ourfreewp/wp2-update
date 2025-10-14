import { updateState } from '../state/store.js';

const MODAL_OPEN_CLASS = 'wp2-modal-open';

export const modalManager = {
    /**
     * Opens a modal with the given content and optional submission logic.
     * @param {string} content - The HTML content for the modal.
     * @param {Function} [onSubmit] - Optional callback for handling form submission.
     */
    open(content, onSubmit) {
        updateState({ modal: { isOpen: true, content } });
        document.body.classList.add(MODAL_OPEN_CLASS);

        const modalContainer = document.querySelector('.wp2-modal-container');
        if (modalContainer) {
            modalContainer.setAttribute('role', 'dialog');
            modalContainer.setAttribute('aria-modal', 'true');
            modalContainer.setAttribute('aria-labelledby', 'wp2-modal-title');
        }

        setTimeout(() => {
            const overlay = document.querySelector('.wp2-modal-overlay');
            overlay?.addEventListener('click', (e) => {
                if (e.target === overlay) {
                    this.close();
                }
            });
        }, 0);

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

        if (onSubmit) {
            const submitButton = modalContainer?.querySelector('.wp2-modal-submit');
            submitButton?.addEventListener('click', async () => {
                submitButton.disabled = true;
                submitButton.innerHTML = '<span class="spinner"></span> Submitting...';

                try {
                    await onSubmit();
                } catch (error) {
                    console.error('Submission failed:', error);
                } finally {
                    submitButton.disabled = false;
                    submitButton.innerHTML = 'Submit';
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
