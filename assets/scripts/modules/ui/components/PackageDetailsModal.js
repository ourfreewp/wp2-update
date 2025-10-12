// PackageDetailsModal.js
// Component for rendering the Package Details modal

const PackageDetailsModal = () => {
    const modal = document.createElement('div');
    modal.id = 'package-details-modal';
    modal.className = 'modal';

    modal.innerHTML = `
        <div class="modal-content">
            <h2>Package Details</h2>
            <p>Details about the selected package will appear here.</p>
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

export default PackageDetailsModal;