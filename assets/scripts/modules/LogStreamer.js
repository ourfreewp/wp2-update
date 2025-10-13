/**
 * Establishes a Server-Sent Events (SSE) connection to the `/logs/stream` endpoint.
 */
export function startLogStream() {
    const apiRoot = window.wp2UpdateData?.apiRoot || '';
    const eventSource = new EventSource(`${apiRoot}/logs/stream`);
    const logContainer = document.getElementById('log-stream-container');

    if (!logContainer) {
        console.error('Log container not found in the DOM.');
        return;
    }

    eventSource.onmessage = (event) => {
        try {
            const log = JSON.parse(event.data);
            if (!log.timestamp || !log.level || !log.message) {
                throw new Error('Invalid log structure');
            }

            const logEntry = document.createElement('div');
            logEntry.className = `log-entry level-${log.level.toLowerCase()}`;
            logEntry.textContent = `[${log.timestamp}] ${log.message}`;
            logContainer.prepend(logEntry);
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