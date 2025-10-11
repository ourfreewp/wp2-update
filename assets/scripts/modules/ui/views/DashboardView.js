import { AppsTable } from '../components/tables/AppsTable';
import { PackagesTable } from '../components/tables/PackagesTable';
import { app_state } from '../../state/store';
import { AppService } from '../../services/AppService';
import { PackageService } from '../../services/PackageService';

/**
 * Returns the dynamic HTML for the dashboard layout.
 * @param {object} state - The application state.
 */
export const DashboardView = (state) => {
    const { apps } = app_state.get();
    const { packages } = state;

    return `
        <div id="wp2-dashboard-container">
            <div id="wp2-apps-section">
                <h2>Apps</h2>
                ${AppsTable(apps)}
            </div>
            <div id="wp2-packages-section">
                <h2>Packages</h2>
                ${PackagesTable(packages)}
            </div>
        </div>
    `;
};

// Fetch apps and packages on load
AppService.fetchApps();
PackageService.fetchPackages();