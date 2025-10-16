// Replace module import with direct usage of wp.i18n
const { __ } = wp.i18n;

// This file is now focused solely on interactions for the Apps tab.

document.addEventListener('click', (event) => {
    if (event.target && event.target.dataset.wp2Action === 'app-details') {
        const appId = event.target.closest('tr').dataset.appId;
        console.log(wp2UpdateData.i18n.appInteraction, appId);

        // Trigger a custom event for app details interaction
        const appDetailsEvent = new CustomEvent('wp2AppDetails', {
            detail: { appId },
        });
        document.dispatchEvent(appDetailsEvent);
    }
});

// Add event listener for the 'Load More' button

document.addEventListener('click', (event) => {
    if (event.target && event.target.id === 'load-more-apps') {
        const button = event.target;
        const currentPage = parseInt(button.dataset.currentPage, 10);
        const nextPage = currentPage + 1;

        // Disable the button to prevent multiple clicks
        button.disabled = true;
        button.textContent = wp2UpdateData.i18n.loading;

        // Fetch the next page of apps
        fetchPaginatedApps(nextPage).then(() => {
            // Update the button state
            button.dataset.currentPage = nextPage;
            button.disabled = false;
            button.textContent = wp2UpdateData.i18n.loadMore;
        }).catch(() => {
            // Handle errors
            button.disabled = false;
            button.textContent = wp2UpdateData.i18n.loadMore;
        });
    }
});

// Add event listener for the "Setup Wizard" button
document.addEventListener('click', (event) => {
    if (event.target && event.target.dataset.wp2Action === 'setup-wizard') {
        const modal = new bootstrap.Modal(document.getElementById('github-app-wizard-modal'));
        modal.show();
    }
});
