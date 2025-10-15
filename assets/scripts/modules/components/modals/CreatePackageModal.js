const { __ } = wp.i18n;
import { StandardModal } from './StandardModal.js';
import { store } from '../../modules/state/store.js';
import { api_request } from '../../api.js';

export const CreatePackageModal = () => {
    const apps = store.get().apps || [];
    const appOptions = apps.map(app => `<option value="${app.id}">${app.name}</option>`).join('');

    const bodyContent = `
        <div class="wizard-step" data-step="1">
            <h3>${__('Step 1: Select Package Type', 'wp2-update')}</h3>
            <p>${__('Select a package type to get started.', 'wp2-update')}</p>
            <select name="template" id="package-type" class="wp2-input">
                <option value="plugin">${__('Plugin', 'wp2-update')}</option>
                <option value="theme">${__('Theme', 'wp2-update')}</option>
            </select>
        </div>
        <div class="wizard-step" data-step="2" style="display: none;">
            <h3>${__('Step 2: Configure Package Details', 'wp2-update')}</h3>
            <label>
                ${__('App Selection:', 'wp2-update')}<br>
                <select id="app-selection" class="wp2-input">
                    ${appOptions}
                </select>
            </label>
            <label>
                ${__('Repository Slug:', 'wp2-update')}<br>
                <input type="text" id="repo-slug" class="wp2-input" placeholder="${__('Enter repository slug', 'wp2-update')}" required />
            </label>
            <label>
                ${__('Initial Branch:', 'wp2-update')}<br>
                <input type="text" id="initial-branch" class="wp2-input" placeholder="${__('Enter initial branch', 'wp2-update')}" required />
            </label>
            <label>
                ${__('Visibility:', 'wp2-update')}<br>
                <select id="visibility" class="wp2-input">
                    <option value="public">${__('Public', 'wp2-update')}</option>
                    <option value="private">${__('Private', 'wp2-update')}</option>
                </select>
            </label>
        </div>
        <div class="wizard-step" data-step="3" style="display: none;">
            <h3>${__('Step 3: Review and Confirm', 'wp2-update')}</h3>
            <p>${__('Please review your package details below:', 'wp2-update')}</p>
            <ul>
                <li>${__('Package Type:', 'wp2-update')} <span id="review-package-type"></span></li>
                <li>${__('App Selection:', 'wp2-update')} <span id="review-app-selection"></span></li>
                <li>${__('Repository Slug:', 'wp2-update')} <span id="review-repo-slug"></span></li>
                <li>${__('Initial Branch:', 'wp2-update')} <span id="review-initial-branch"></span></li>
                <li>${__('Visibility:', 'wp2-update')} <span id="review-visibility"></span></li>
            </ul>
        </div>
    `;

    const footerActions = [
        { label: __('Back', 'wp2-update'), class: 'wp2-btn--secondary wizard-back-btn', attributes: 'id="wizard-back-step" style="display: none;"' },
        { label: __('Next', 'wp2-update'), class: 'wp2-btn--primary wizard-next-btn', attributes: 'id="wizard-next-step"' },
        { label: __('Finish', 'wp2-update'), class: 'wp2-btn--primary wizard-finish-btn', attributes: 'id="wizard-finish-step" style="display: none;"' }
    ];

    const modal = StandardModal({
        title: __('Create New Package', 'wp2-update'),
        bodyContent,
        footerActions
    });

    modal.addEventListener('click', async (event) => {
        if (event.target.id === 'wizard-next-step') {
            const currentStep = document.querySelector('.wizard-step:not([style*="display: none;"])');
            const nextStep = currentStep.nextElementSibling;
            if (nextStep) {
                currentStep.style.display = 'none';
                nextStep.style.display = 'block';

                if (nextStep.dataset.step === '3') {
                    document.getElementById('review-package-type').textContent = document.getElementById('package-type').value;
                    document.getElementById('review-app-selection').textContent = document.getElementById('app-selection').value;
                    document.getElementById('review-repo-slug').textContent = document.getElementById('repo-slug').value;
                    document.getElementById('review-initial-branch').textContent = document.getElementById('initial-branch').value;
                    document.getElementById('review-visibility').textContent = document.getElementById('visibility').value;
                }
            }
        } else if (event.target.id === 'wizard-back-step') {
            const currentStep = document.querySelector('.wizard-step:not([style*="display: none;"])');
            const prevStep = currentStep.previousElementSibling;
            if (prevStep) {
                currentStep.style.display = 'none';
                prevStep.style.display = 'block';
            }
        } else if (event.target.id === 'wizard-finish-step') {
            const packageType = document.getElementById('package-type').value;
            const appId = document.getElementById('app-selection').value;
            const repoSlug = document.getElementById('repo-slug').value;
            const initialBranch = document.getElementById('initial-branch').value;
            const visibility = document.getElementById('visibility').value;

            if (!repoSlug || !initialBranch) {
                alert(__('Please fill in all required fields.', 'wp2-update'));
                return;
            }

            try {
                await api_request('packages/create', {
                    method: 'POST',
                    body: JSON.stringify({
                        package_type: packageType,
                        app_id: appId,
                        repo_slug: repoSlug,
                        initial_branch: initialBranch,
                        visibility: visibility
                    })
                }, 'wp2_create_package');

                alert(__('Package created successfully!', 'wp2-update'));
                modal.dispatchEvent(new Event('close'));
            } catch (error) {
                alert(__('Failed to create package. Please try again.', 'wp2-update'));
            }
        }
    });

    return modal;
};
