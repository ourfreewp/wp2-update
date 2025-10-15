import { escapeHtml } from '../../utils/string.js';
import { StandardModal } from './StandardModal.js';
import { PackageService } from '../../services/PackageService.js';
import { apiFetch } from '@wordpress/api-fetch';

export const PackageDetailsModal = (pkg) => {
    const bodyContent = `
        <ul class="nav nav-tabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#details-tab" type="button" role="tab">Details</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#release-notes-tab" type="button" role="tab">Release Notes</button>
            </li>
        </ul>
        <div class="tab-content">
            <div class="tab-pane fade show active" id="details-tab" role="tabpanel">
                <dl class="wp2-detail-grid">
                    <dt>Repository</dt><dd>${escapeHtml(pkg.repo)}</dd>
                    <dt>Installed Version</dt><dd>${escapeHtml(pkg.version || 'N/A')}</dd>
                    <dt>Latest Version</dt><dd>${escapeHtml(pkg.latest || 'N/A')}</dd>
                </dl>
            </div>
            <div class="tab-pane fade" id="release-notes-tab" role="tabpanel">
                <p>Loading release notes...</p>
            </div>
        </div>
    `;

    const footerActions = [
        { label: 'Close', class: 'wp2-btn--secondary', attributes: 'data-dismiss="modal"' }
    ];

    const modal = StandardModal({
        title: escapeHtml(pkg.name),
        bodyContent,
        footerActions
    });

    modal.addEventListener('shown.bs.tab', (event) => {
        if (event.target.getAttribute('data-bs-target') === '#release-notes-tab') {
            const releaseNotesContainer = modal.querySelector('#release-notes-tab');
            releaseNotesContainer.innerHTML = '<p>Loading release notes...</p>';

            fetchReleaseNotes(pkg.repo)
                .then((notes) => {
                    releaseNotesContainer.innerHTML = notes.length
                        ? `<ul>${notes.map(note => `<li>${escapeHtml(note)}</li>`).join('')}</ul>`
                        : '<p>No release notes available.</p>';
                })
                .catch(() => {
                    releaseNotesContainer.innerHTML = '<p>Failed to load release notes.</p>';
                });
        }
    });

    async function fetchReleaseNotes(repoSlug) {
        try {
            const response = await apiFetch({
                path: `/wp-json/wp2-update/v1/packages/${repoSlug}/release-notes`,
                method: 'GET'
            });

            return response.notes || __('No release notes available.', 'wp2-update');
        } catch (error) {
            throw new Error(__('Failed to fetch release notes. Please try again.', 'wp2-update'));
        }
    }

    return modal;
};
