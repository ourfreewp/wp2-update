export const DashboardView = {
    initialize: () => {
        console.log('Dashboard interactions initialized.');

        document.addEventListener('click', (event) => {
            if (event.target && event.target.dataset.wp2Action === 'refresh-widgets') {
                console.log('Refresh widgets interaction triggered.');

                // Trigger a custom event for refreshing widgets
                const refreshEvent = new CustomEvent('wp2RefreshWidgets');
                document.dispatchEvent(refreshEvent);
            }
        });
    }
};