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
    const logContainer = document.getElementById('log-viewer');

    if (!logContainer) {
        console.error('Log viewer container not found in the DOM.');
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
            logEntry.innerHTML = `
                <span class="log-timestamp">[${log.timestamp}]</span>
                <span class="log-level level-${log.level.toLowerCase()}">${log.level}</span>
                <span class="log-message">${log.message}</span>
            `;
            logContainer.appendChild(logEntry);
            logContainer.scrollTop = logContainer.scrollHeight; // Auto-scroll to the bottom
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