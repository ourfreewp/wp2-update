import apiFetch from '@wordpress/api-fetch';

export const PollingService = {
    startPolling: (url, interval, callback) => {
        async function poll() {
            try {
                const data = await apiFetch({ path: url });
                callback(null, data);
            } catch (error) {
                callback(error, null);
            }
        }

        // Start the polling loop
        const pollingInterval = setInterval(poll, interval);
        poll(); // Initial call

        // Return a function to stop polling
        return () => clearInterval(pollingInterval);
    }
};