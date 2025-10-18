import { debounce } from '../utils/debounce.js';

export const PollingService = {
    // Supports both legacy signature (url, interval, callback)
    // and object signature { endpoint, interval, onSuccess, onError }
    startPolling: (arg1, arg2, arg3) => {
        let endpoint, interval, onSuccess, onError;
        if (typeof arg1 === 'object') {
            ({ endpoint, interval, onSuccess, onError } = arg1);
        } else {
            endpoint = arg1;
            interval = arg2;
            onSuccess = arg3;
        }

        let pollingInterval = null;
        let isPaused = false;
        let lastFetchedData = null;

        const debouncedSuccess = debounce((data) => onSuccess && onSuccess(data), 300);

        async function poll() {
            if (isPaused) return;

            try {
                const data = await apiFetch({ path: endpoint });
                if (JSON.stringify(data) !== JSON.stringify(lastFetchedData)) {
                    lastFetchedData = data;
                    debouncedSuccess(data);
                }
            } catch (error) {
                if (onError) onError(error);
            }
        }

        pollingInterval = setInterval(poll, interval);
        poll();

        return {
            stopPolling: () => clearInterval(pollingInterval),
            pausePolling: () => { isPaused = true; },
            resumePolling: () => { isPaused = false; poll(); }
        };
    }
};
