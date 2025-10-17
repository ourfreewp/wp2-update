import { updateState } from '../state/store.js';

/**
 * Establishes a Server-Sent Events (SSE) connection to the `/logs/stream` endpoint.
 */
export function startLogStream() {
    const apiRoot = window.wp2UpdateData?.apiRoot || '';
    // Ensure the API root is defined
    if (!apiRoot) {
        console.error('API Root is undefined. Cannot start log stream.');
        return;
    }
    const eventSource = new EventSource(`${apiRoot}/logs/stream`);

    eventSource.onmessage = (event) => {
        try {
            const log = JSON.parse(event.data);
            if (!log.timestamp || !log.level || !log.message) {
                throw new Error('Invalid log structure');
            }

            updateState((prevState) => ({
                logs: [...prevState.logs, log]
            }));
        } catch (error) {
            console.error('Failed to process log entry:', error);
        }
    };

    eventSource.onerror = () => {
        console.error('Log stream connection lost. Attempting to reconnect...');
        eventSource.close();
        setTimeout(startLogStream, 5000); // Retry connection after 5 seconds
    };
}