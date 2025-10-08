(function () {
    // Parse the URL parameters
    const params = new URLSearchParams(window.location.search);
    const code = params.get('code');

    if (!code) {
        console.error('GitHub callback: Missing code parameter.');
        return;
    }

    // Send the code to the backend using apiRequest
    apiRequest('wp2_update_save_github_code', {
        body: JSON.stringify({
            code: code,
        }),
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