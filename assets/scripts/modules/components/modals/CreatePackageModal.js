const { __ } = wp.i18n;
import { store } from '../../state/store.js';

export const CreatePackageModal = () => {
    const modalContent = `
        <div class="wp2-modal-header">
            <h2>${__('Create New Package', 'wp2-update')}</h2>
        </div>
        <div class="wp2-modal-body">
            <div class="wizard-step" data-step="1">
                <p>${__('Select a package type to get started.', 'wp2-update')}</p>
                <select name="template" class="wp2-input">
                    <option value="plugin">${__('Plugin', 'wp2-update')}</option>
                    <option value="theme">${__('Theme', 'wp2-update')}</option>
                </select>
                <div class="wp2-modal-actions">
                    <button type="button" class="wp2-btn wp2-btn--primary wizard-next-btn">${__('Next', 'wp2-update')}</button>
                </div>
            </div>
            <div class="wizard-step" data-step="2" style="display: none;">
                <p>${__('Configure your package details.', 'wp2-update')}</p>
                <input type="text" name="package-name" class="wp2-input" placeholder="${__('Package Name', 'wp2-update')}" required />
                <div class="wp2-modal-actions">
                    <button type="button" class="wp2-btn wp2-btn--secondary wizard-back-btn">${__('Back', 'wp2-update')}</button>
                    <button type="button" class="wp2-btn wp2-btn--primary wizard-next-btn">${__('Next', 'wp2-update')}</button>
                </div>
            </div>
            <div class="wizard-step" data-step="3" style="display: none;">
                <p>${__('Review and confirm your package creation.', 'wp2-update')}</p>
                <div class="wp2-modal-actions">
                    <button type="button" class="wp2-btn wp2-btn--secondary wizard-back-btn">${__('Back', 'wp2-update')}</button>
                    <button type="button" class="wp2-btn wp2-btn--primary wizard-finish-btn">${__('Finish', 'wp2-update')}</button>
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
            console.log(__('Package creation finished!', 'wp2-update'));
            // Add logic to handle package creation submission
        }
    });

    showStep(currentStep);
    return modalElement;
};
