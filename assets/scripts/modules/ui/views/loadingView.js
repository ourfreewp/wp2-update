const { __ } = window.wp?.i18n ?? { __: (text) => text }; // Corrected from i1n to i18n

export const loadingView = () => `
    <div class="wp2-dashboard-card wp2-dashboard-loading">
        <div class="wp2-dashboard-spinner"></div>
        <p>${__('Loading connection status…', 'wp2-update')}</p>
    </div>
`;
