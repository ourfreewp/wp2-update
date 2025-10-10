import { escapeHTML } from '../../../utils.js';

const { __ } = window.wp?.i18n ?? { __: (text) => text };

export const detectedPackagesSection = (packages) => `
    <section class="wp2-dashboard-card wp2-card-table">
        <h3>${__('Detected Packages', 'wp2-update')}</h3>
        <p class="wp2-muted">${__('We found themes or plugins that can be managed once you connect to GitHub.', 'wp2-update')}</p>
        <div class="wp2-table-wrapper">
            <table class="wp2-table">
                <thead>
                    <tr>
                        <th>${__('Package', 'wp2-update')}</th>
                        <th>${__('Installed Version', 'wp2-update')}</th>
                        <th>${__('Repository', 'wp2-update')}</th>
                    </tr>
                </thead>
                <tbody>
                    ${packages.map(pkg => `
                        <tr>
                            <td>${escapeHTML(pkg.name)}</td>
                            <td>${pkg.version ? `v${escapeHTML(pkg.version)}` : __('Unknown', 'wp2-update')}</td>
                            <td>${pkg.repo ? `<code>${escapeHTML(pkg.repo)}</code>` : '&mdash;'}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    </section>
`;
