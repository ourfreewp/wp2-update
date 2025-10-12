export function handleViewReleaseNotes(event) {
    const button = event.target;
    const packageId = button.dataset.packageId;

    fetch(`/wp-json/wp2-update/v1/packages/${packageId}/release-notes`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Failed to fetch release notes.');
            }
            return response.json();
        })
        .then(data => {
            const modalContent = document.getElementById('wp2-release-notes-content');
            modalContent.innerHTML = data.notes || '<p>No release notes available.</p>';

            const modal = document.getElementById('wp2-release-notes-modal');
            modal.hidden = false;
        })
        .catch(error => {
            console.error('Error fetching release notes:', error);
        });
}