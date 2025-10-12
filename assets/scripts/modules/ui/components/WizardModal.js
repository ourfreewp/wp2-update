// WizardModal.js
// Component for rendering the Wizard modal

const WizardModal = () => {
    const modal = document.createElement('div');
    modal.id = 'wizard-modal';
    modal.className = 'modal';

    modal.innerHTML = `
        <div class="modal-content">
            <h2>Wizard</h2>
            <p>Multi-step wizard content goes here.</p>
        </div>
        <div class="modal-footer">
            <button class="modal-close">Close</button>
        </div>
    `;

    modal.querySelector('.modal-close').addEventListener('click', () => {
        modal.classList.remove('is-active');
    });

    return modal;
};

export default WizardModal;