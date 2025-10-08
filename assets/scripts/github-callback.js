(function () {
    // Parse the URL parameters
    const params = new URLSearchParams(window.location.search);
    const code = params.get('code');

    if (!code) {
        console.error('GitHub callback: Missing code parameter.');
        return;
    }

    // Send the code to the backend
    fetch(ajaxurl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'wp2_update_save_github_code',
            code: code,
        }),
    })
        .then((response) => {
            if (!response.ok) {
                throw new Error('Failed to save GitHub code.');
            }
            return response.json();
        })
        .then((data) => {
            console.log('GitHub code saved successfully:', data);
            // Redirect back to the main settings page
            window.location.href = 'admin.php?page=wp2-update-settings';
        })
        .catch((error) => {
            console.error('Error during GitHub callback handling:', error);
        });
})();