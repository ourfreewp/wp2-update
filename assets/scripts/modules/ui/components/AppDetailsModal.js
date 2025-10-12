// AppDetailsModal.js
// Component for rendering the App Details modal

const AppDetailsModal = () => {
    const modal = document.createElement('div');
    modal.id = 'app-details-modal';
    modal.className = 'modal';

    modal.innerHTML = `
        <div class="modal-content">
            <h2>App Details</h2>
            <p>Details about the selected app will appear here.</p>
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

export default AppDetailsModal;