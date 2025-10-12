// Event handlers related to wizard modals

const MODAL_ACTIVE_CLASS = 'is-active';

export const setBodyModalState = (isOpen) => {
    document.body.classList.toggle('wp2-modal-open', isOpen);
};

export const openModal = (id) => {
    const modal = document.getElementById(id);
    if (!modal) {
        return;
    }
    modal.classList.add(MODAL_ACTIVE_CLASS);
    setBodyModalState(true);
};