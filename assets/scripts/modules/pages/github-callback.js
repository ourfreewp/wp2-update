(function () {
    // Parse the URL parameters
    const params = new URLSearchParams(window.location.search);
    const code = params.get('code');

    if (!code) {
        console.error('GitHub callback: Missing code parameter.');
        alert('Error: Missing code parameter in the URL. Please try again.');
        return;
    }

    // Send the code to the backend using the correct REST endpoint
    fetch(`${window.wpApiSettings.root}wp2-update/v1/github/exchange-code`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': window.wpApiSettings.nonce,
        },
        body: JSON.stringify({
            code: code,
        }),
    })
        .then((response) => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then((data) => {
            console.log('GitHub code exchanged successfully:', data);
            // Redirect back to the main settings page
            window.location.href = 'admin.php?page=wp2-update';
        })
        .catch((error) => {
            console.error('Error during GitHub callback handling:', error);
            alert('An error occurred while processing the GitHub callback. Please try again later.');
        });
})();