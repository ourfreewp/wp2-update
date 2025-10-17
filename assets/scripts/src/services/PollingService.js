import { debounce } from '../utils/debounce.js';

export const PollingService = {
    startPolling: (url, interval, callback) => {
        let pollingInterval = null;
        let isPaused = false;
        let lastFetchedData = null;

        const debouncedCallback = debounce(callback, 300);

        async function poll() {
            if (isPaused) return;

            try {
                const data = await apiFetch({ path: url });

                // Avoid redundant updates if data hasn't changed
                if (JSON.stringify(data) !== JSON.stringify(lastFetchedData)) {
                    lastFetchedData = data;
                    debouncedCallback(null, data);
                }
            } catch (error) {
                debouncedCallback(error, null);
            }
        }

        // Start the polling loop
        pollingInterval = setInterval(poll, interval);
        poll(); // Initial call

        return {
            stopPolling: () => clearInterval(pollingInterval),
            pausePolling: () => { isPaused = true; },
            resumePolling: () => { isPaused = false; poll(); }
        };
    }
};
