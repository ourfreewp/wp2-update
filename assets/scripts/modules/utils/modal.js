import { updateState } from '../state/store.js';

const getBootstrapModalInstance = (modalId) => {
    const modalElement = document.querySelector(`#${modalId}`);
    if (!modalElement || typeof bootstrap === 'undefined' || !bootstrap.Modal) {
        console.error(`Bootstrap Modal is not available or target element #${modalId} not found.`);
        return null;
    }
    return bootstrap.Modal.getOrCreateInstance(modalElement, {
        keyboard: true,
        focus: true
    });
};

document.addEventListener('hidden.bs.modal', (event) => {
    const modalId = event.target.id;
    updateState({ modal: { id: modalId, isOpen: false, content: null } });
    document.body.classList.remove('wp2-modal-open');
});

export const modalManager = {
    open(modalId, content, onSubmit) {
        const modalInstance = getBootstrapModalInstance(modalId);
        const modalContent = document.querySelector(`#${modalId} .modal-content`);

        if (!modalInstance || !modalContent) return;

        modalContent.innerHTML = content;
        modalInstance.show();

        updateState({ modal: { id: modalId, isOpen: true, content } });
        document.body.classList.add('wp2-modal-open');

        if (onSubmit) {
            const submitButton = modalContent.querySelector('.wp2-modal-submit');
            submitButton?.addEventListener('click', async (e) => {
                e.preventDefault();
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

    close(modalId) {
        const modalInstance = getBootstrapModalInstance(modalId);
        modalInstance?.hide();
    }
};
