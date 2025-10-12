// Event handlers related to package-specific actions

export const toggleOrgFields = (accountType) => {
    document.querySelectorAll('.wp2-org-field').forEach((field) => {
        field.hidden = accountType !== 'organization';
    });
};

// Handles event listeners for package-related actions

const MODAL_ACTIVE_CLASS = 'is-active';

const setBodyModalState = (isOpen) => {
    document.body.classList.toggle('wp2-modal-open', isOpen);
};

const openModal = (id) => {
    const modal = document.getElementById(id);
    if (!modal) {
        return;
    }

    modal.hidden = false;
    modal.classList.add(MODAL_ACTIVE_CLASS);
    setBodyModalState(true);
};

const closeModal = (id) => {
    const modal = document.getElementById(id);
    if (!modal) {
        return;
    }
    modal.classList.remove(MODAL_ACTIVE_CLASS);
    modal.hidden = true;

    const anyOpen = Array.from(document.querySelectorAll('.wp2-modal-overlay'))
        .some(node => node.classList.contains(MODAL_ACTIVE_CLASS) && !node.hidden);
    if (!anyOpen) {
        setBodyModalState(false);
    }
};

export { openModal, closeModal };