export const CreatePackageModal = () => {
    const modalContent = `
        <div class="wp2-modal-header">
            <h2>Create New Package</h2>
        </div>
        <div class="wp2-modal-body">
            <div class="wizard-step" data-step="1">
                <p>Select a package type to get started.</p>
                <select name="template" class="wp2-input">
                    <option value="plugin">Plugin</option>
                    <option value="theme">Theme</option>
                </select>
                <div class="wp2-modal-actions">
                    <button type="button" class="wp2-btn wp2-btn--primary wizard-next-btn">Next</button>
                </div>
            </div>
            <div class="wizard-step" data-step="2" style="display: none;">
                <p>Configure your package details.</p>
                <input type="text" name="package-name" class="wp2-input" placeholder="Package Name" required />
                <div class="wp2-modal-actions">
                    <button type="button" class="wp2-btn wp2-btn--secondary wizard-back-btn">Back</button>
                    <button type="button" class="wp2-btn wp2-btn--primary wizard-next-btn">Next</button>
                </div>
            </div>
            <div class="wizard-step" data-step="3" style="display: none;">
                <p>Review and confirm your package creation.</p>
                <div class="wp2-modal-actions">
                    <button type="button" class="wp2-btn wp2-btn--secondary wizard-back-btn">Back</button>
                    <button type="button" class="wp2-btn wp2-btn--primary wizard-finish-btn">Finish</button>
                </div>
            </div>
        </div>
    `;

    const modalElement = document.createElement('div');
    modalElement.innerHTML = modalContent;

    const steps = modalElement.querySelectorAll('.wizard-step');
    let currentStep = 1;

    const showStep = (step) => {
        steps.forEach((stepElement) => {
            stepElement.style.display = stepElement.dataset.step == step ? 'block' : 'none';
        });
    };

    modalElement.addEventListener('click', (event) => {
        if (event.target.classList.contains('wizard-next-btn')) {
            currentStep++;
            showStep(currentStep);
        } else if (event.target.classList.contains('wizard-back-btn')) {
            currentStep--;
            showStep(currentStep);
        } else if (event.target.classList.contains('wizard-finish-btn')) {
            console.log('Package creation finished!');
            // Add logic to handle package creation submission
        }
    });

    showStep(currentStep);
    return modalElement;
};
