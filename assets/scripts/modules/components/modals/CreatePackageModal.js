const { __ } = wp.i18n;
import { StandardModal } from './StandardModal.js';
import { store } from '../../state/store.js';
import { apiFetch } from '@wordpress/api-fetch';

export const CreatePackageModal = () => {
    const apps = Array.isArray(store.get().apps) ? store.get().apps : [];
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
    `;

    const footerActions = [
        { label: __('Cancel', 'wp2-update'), class: 'wp2-btn--secondary', attributes: 'data-dismiss="modal"' },
        { label: __('Create Package', 'wp2-update'), class: 'wp2-btn--primary', attributes: 'id="create-package-btn"' },
    ];

    const modal = StandardModal({
        title: __('Create New Package', 'wp2-update'),
        bodyContent,
        footerActions,
    });

    document.getElementById('create-package-btn').addEventListener('click', async () => {
        const repoSlug = document.getElementById('repo-slug').value;
        const appId = document.getElementById('app-selection').value;
        const initialBranch = document.getElementById('initial-branch').value;
        const visibility = document.getElementById('visibility').value;

        try {
            await apiFetch({
                path: '/wp2-update/v1/packages',
                method: 'POST',
                data: { repo_slug: repoSlug, app_id: appId, initial_branch: initialBranch, visibility },
            });
            alert(__('Package created successfully!', 'wp2-update'));
        } catch (error) {
            alert(__('Failed to create package.', 'wp2-update'));
        }
    });

    return modal;
};
