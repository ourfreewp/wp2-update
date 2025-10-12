export function handleAutoUpdateToggle(event) {
    const toggle = event.target;
    const packageId = toggle.dataset.packageId;
    const isEnabled = toggle.checked;

    fetch(`/wp-json/wp2-update/v1/packages/${packageId}/auto-update`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ auto_update: isEnabled }),
    })
        .then(response => {
            if (!response.ok) {
                throw new Error('Failed to update auto-update setting.');
            }
            return response.json();
        })
        .then(data => {
            console.log('Auto-update setting updated:', data);
        })
        .catch(error => {
            console.error('Error updating auto-update setting:', error);
            toggle.checked = !isEnabled; // Revert toggle on error
        });
}