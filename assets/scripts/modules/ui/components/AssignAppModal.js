// AssignAppModal.js
// Component for rendering the Assign App modal

const AssignAppModal = () => {
    const modal = document.createElement('div');
    modal.id = 'assign-app-modal';
    modal.className = 'modal';

    modal.innerHTML = `
        <div class="modal-content">
            <h2>Assign App</h2>
            <form>
                <label for="app-name">App Name:</label>
                <input type="text" id="app-name" name="app-name" required>
                <button type="submit">Assign</button>
            </form>
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

export default AssignAppModal;