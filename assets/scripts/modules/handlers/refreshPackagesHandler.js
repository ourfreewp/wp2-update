/**
 * Handles the refresh packages button click.
 */
export function handleRefreshPackages() {
    const refreshButton = document.getElementById('wp2-refresh-packages');

    if (!refreshButton) {
        return;
    }

    refreshButton.addEventListener('click', async () => {
        refreshButton.disabled = true;
        refreshButton.innerHTML = '<i class="bi bi-arrow-clockwise me-1"></i> Refreshing...';

        try {
            const response = await apiFetch({
                path: '/wp2-update/v1/refresh-packages',
                method: 'POST',
            });

            if (response.success) {
                alert(response.message);
                location.reload();
            } else {
                alert('Failed to refresh packages: ' + response.message);
            }
        } catch (error) {
            console.error('Error refreshing packages:', error);
            alert('An error occurred while refreshing packages.');
        } finally {
            refreshButton.disabled = false;
            refreshButton.innerHTML = '<i class="bi bi-arrow-clockwise me-1"></i> Refresh';
        }
    });
}
