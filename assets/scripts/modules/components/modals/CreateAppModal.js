const { __ } = wp.i18n;
import { modalManager } from '../../utils/modal.js';
import { StandardModal } from './StandardModal.js';
import { ManualCredentialsModal } from './ManualCredentialsModal.js';
import { apiFetch } from '@wordpress/api-fetch';

/**
 * Initializes the GitHub App Setup Wizard modal.
 */
export function initializeWizardModal() {
    const bodyContent = `
        <div id="wizard-steps-container">
            <div class="wizard-step" data-step="1">
                <h3>${__('Step 1: Choose setup method', 'wp2-update')}</h3>
                <label>
                    <input type="radio" name="setup-method" value="auto" checked>
                    ${__('Auto-setup (Recommended)', 'wp2-update')}
                </label>
                <label>
                    <input type="radio" name="setup-method" value="manual">
                    ${__('Manual Setup', 'wp2-update')}
                </label>
            </div>
            <div class="wizard-step" data-step="2" style="display: none;">
                <h3>${__('Step 2: Configure details', 'wp2-update')}</h3>
                <label>
                    ${__('App Name:', 'wp2-update')}<br>
                    <input type="text" id="app-name" placeholder="${__('Enter your app name', 'wp2-update')}" required>
                </label>
                <label>
                    ${__('Organization/Account Type:', 'wp2-update')}<br>
                    <select id="account-type">
                        <option value="organization">${__('Organization', 'wp2-update')}</option>
                        <option value="personal">${__('Personal', 'wp2-update')}</option>
                    </select>
                </label>
                <label>
                    ${__('App Manifest:', 'wp2-update')}<br>
                    <textarea id="app-manifest" rows="10" readonly></textarea>
                </label>
            </div>
        </div>
    `;

    const footerActions = [
        { label: __('Back', 'wp2-update'), class: 'wp2-btn--secondary', attributes: 'id="wizard-back-step" style="display: none;"' },
        { label: __('Next', 'wp2-update'), class: 'wp2-btn--primary', attributes: 'id="wizard-next-step"' },
        { label: __('Submit', 'wp2-update'), class: 'wp2-btn--primary wp2-modal-submit', attributes: 'id="wizard-submit" style="display: none;"' }
    ];

    const modalContent = StandardModal({
        title: __('GitHub App Setup Wizard', 'wp2-update'),
        bodyContent,
        footerActions
    });

    modalManager.open('addAppModal', modalContent);

    let currentStep = 1;
    const steps = document.querySelectorAll('.wizard-step');

    function updateWizardStepUI(step) {
        steps.forEach((stepElement, index) => {
            stepElement.style.display = index + 1 === step ? 'block' : 'none';
        });

        document.querySelector('#wizard-back-step').style.display = step > 1 ? 'inline-block' : 'none';
        document.querySelector('#wizard-next-step').style.display = step < steps.length ? 'inline-block' : 'none';
        document.querySelector('#wizard-submit').style.display = step === steps.length ? 'inline-block' : 'none';

        if (step === 3) {
            document.getElementById('review-app-name').textContent = document.getElementById('app-name').value;
            document.getElementById('review-account-type').textContent = document.getElementById('account-type').value;
        }
    }

    const renderStep = () => {
        updateWizardStepUI(currentStep);
    };

    document.querySelector('#wizard-back-step').addEventListener('click', () => {
        if (currentStep > 1) {
            currentStep--;
            renderStep();
        }
    });

    document.querySelector('#wizard-next-step').addEventListener('click', async () => {
        if (currentStep === 1) {
            const selectedMethod = document.querySelector('input[name="setup-method"]:checked').value;
            if (selectedMethod === 'manual') {
                modalManager.close('addAppModal');
                modalManager.open('manualCredentialsModal', ManualCredentialsModal());
                return;
            }
        }

        if (currentStep === 2) {
            const appName = document.getElementById('app-name').value;
            const accountType = document.getElementById('account-type').value;

            if (!appName) {
                alert(__('App Name is required.', 'wp2-update'));
                return;
            }

            try {
                const response = await apiFetch({
                    path: '/wp2-update/v1/apps/manifest',
                    method: 'POST',
                    data: { name: appName, account_type: accountType },
                });

                const manifestTextarea = document.getElementById('app-manifest');
                manifestTextarea.value = response.setup_url;
                manifestTextarea.readOnly = true;

                if (response.manifest_url) {
                    const manifestLink = document.getElementById('manifest-url');
                    if (manifestLink) {
                        manifestLink.setAttribute('href', response.manifest_url);
                        document.getElementById('review-app-manifest-url').textContent = response.manifest_url;
                    } else {
                        NotificationService.showError(__('Wizard link missing.', 'wp2-update'));
                    }
                }
            } catch (error) {
                alert(__('Failed to generate manifest. Please try again.', 'wp2-update'));
                return;
            }
        }

        if (currentStep < steps.length) {
            currentStep++;
            renderStep();
        }
    });

    document.querySelector('#wizard-submit').addEventListener('click', () => {
        modalManager.close('addAppModal');
        alert(__('GitHub App setup completed!', 'wp2-update'));
    });

    renderStep();
}
