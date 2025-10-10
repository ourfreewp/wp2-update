const { __ } = window.wp?.i18n ?? { __: (text) => text };

export const connectingView = () => `
    <section class="wp2-dashboard-card wp2-card-centered" role="region" aria-labelledby="connecting-heading">
        <h1 id="connecting-heading" class="screen-reader-text">${__('Connecting to GitHub', 'wp2-update')}</h1>
        <div class="wp2-dashboard-spinner wp2-dashboard-spinner-lg"></div>
        <h2>${__('Finalizing Connection…', 'wp2-update')}</h2>
        <p>${__('Verifying your GitHub App credentials and installation.', 'wp2-update')}</p>
    </section>
`;
