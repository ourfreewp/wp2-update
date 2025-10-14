import { HealthService } from '../services/HealthService.js';
import { store } from '../state/store.js';

// This file is now focused solely on interactions for the Health tab.

export const HealthView = {
    initialize: () => {
        console.log('Health interactions initialized.');

        const healthContainer = document.querySelector('#health-container');

        // Function to render health data dynamically
        const renderHealthData = () => {
            const healthData = store.get().health;
            healthContainer.innerHTML = healthData
                ? `<pre>${JSON.stringify(healthData, null, 2)}</pre>`
                : '<p>No health data available.</p>';
        };

        // Initial fetch and render
        HealthService.fetchHealthStatus().then(renderHealthData);

        // Listen for state updates
        store.subscribe(renderHealthData);

        document.addEventListener('click', (event) => {
            if (event.target && event.target.dataset.wp2Action === 'refresh-health') {
                console.log('Refresh health checks interaction triggered.');

                // Fetch and render health data on refresh
                HealthService.fetchHealthStatus();
            }
        });
    },
};
