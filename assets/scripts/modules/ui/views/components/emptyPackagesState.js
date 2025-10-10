const { __ } = window.wp?.i18n ?? { __: (text) => text };

export const emptyPackagesState = () => `
    <div class="wp2-empty-state">
        <p>${__('No managed packages found yet. Sync repositories to view release information.', 'wp2-update')}</p>
    </div>
`;
