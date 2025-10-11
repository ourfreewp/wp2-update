# WP2 Update Plugin

WP2 Update is a powerful WordPress plugin that simplifies managing theme and plugin updates directly from your private and public GitHub repositories. With a modern interface and a guided setup, you can connect and manage multiple GitHub Apps, assign them to specific packages, and keep your site up-to-date with ease.

## Features

- **Manage Multiple GitHub Apps:** Connect as many GitHub Apps as you need. Ideal for developers and agencies managing packages from different GitHub accounts or organizations.
- **Assign Apps to Packages:** Granularly assign a specific GitHub App to manage the updates for each theme or plugin.
- **Guided Setup Wizard:** An intuitive, multi-step wizard makes creating and configuring a new GitHub App a breeze, right from the WordPress dashboard.
- **Unified Package Dashboard:** View all your detected and managed packages in a single, streamlined table.
- **Detailed Troubleshooting Modals:** Click on any package or app to view detailed information, including sync status and logs, to quickly diagnose any issues.
- **Modern & Accessible UI:** Built with a clean, responsive interface using accessible components for a seamless user experience.

## Installation and Setup

### Prerequisites

- A GitHub account with permissions to create GitHub Apps.
- Administrator access to your WordPress site.

### Configuration

WP2 Update features a guided wizard to make setup fast and easy.

1. Install and activate the WP2 Update plugin on your WordPress site.
2. Navigate to **Settings > WP2 Update**.
3. Go to the **GitHub Apps** tab and click the **Add New App** button.
4. Follow the on-screen wizard. It will guide you through configuring your app's details and then direct you to GitHub to finalize the creation and installation.
5. Once you complete the process on GitHub, return to the WordPress dashboard. Your new app will be listed and ready to be assigned to packages.

## Multi-App Workflow

WP2 Update now supports managing multiple GitHub Apps, making it easier to organize and assign repositories across different apps. This feature is ideal for developers managing packages from multiple GitHub accounts or organizations.

### Creating and Managing Apps

1. Navigate to **Settings > WP2 Update > GitHub Apps**.
2. Click **Add New App** to launch the guided wizard.
3. Follow the steps to configure your app details and finalize the setup on GitHub.
4. Once the app is created, it will appear in the **GitHub Apps** tab.
5. Use the **Edit** or **Delete** options to manage existing apps.

### Assigning Repositories to Apps

1. Go to the **Packages** tab to view all detected themes and plugins.
2. For unmanaged packages, click **Assign App**.
3. Select the desired GitHub App from the modal and confirm the assignment.
4. The plugin will automatically fetch the latest release information for the assigned app.

### New REST API Endpoints

The following endpoints have been added to support the multi-app workflow:

- **POST /apps**: Create a new GitHub App.
- **PUT /apps/<id>**: Update app details (e.g., name, status, organization).
- **DELETE /apps/<id>**: Remove an app and unassign its repositories.
- **POST /packages/assign**: Assign a repository to a specific GitHub App.

### `requires_installation` Flag

Some GitHub Apps may require installation before they can manage repositories. The `requires_installation` flag indicates whether an app needs to be installed. If required:

1. Follow the installation URL provided in the wizard.
2. Once installed, the app will be ready to manage repositories.

### Troubleshooting

- Use the **Details** modal in the **GitHub Apps** tab to view installation status, webhook status, and other key information.
- Check the **Logs** tab for detailed error messages and debugging information.

For more information, refer to the official documentation or contact support.

## Usage

### Managing Packages

- All your themes and plugins with a GitHub URI header will be automatically detected and listed in the **Packages** tab.
- **Unmanaged Packages:** For newly detected packages, click the **Assign App** button. A modal will appear, allowing you to select which of your configured GitHub Apps should be responsible for checking for updates.
- **Managed Packages:** Once an app is assigned, the plugin will automatically fetch the latest release information. If an update is available, an **Update** button will appear.

### Managing GitHub Apps

The **GitHub Apps** tab provides a central location to manage all your connections.

- **View Apps:** See a list of all your connected GitHub Apps, including the number of packages each one manages.
- **Add New Apps:** Click the **Add New App** button at any time to launch the wizard and connect another app.
- **Troubleshoot:** Click on any app to open a details modal, which provides key information like the Installation ID and Webhook Status for easy troubleshooting.

## Dynamic State Management

WP2 Update introduces dynamic state management to enhance the multi-app workflow. The plugin now uses a centralized state store to manage app selection, repository assignments, and UI updates in real-time.

### App Selection Dropdown

- The **GitHub Apps** tab includes a dropdown to select the active app.
- Selecting an app dynamically updates the dashboard and routes all subsequent actions (e.g., form submissions, repository assignments) to the selected app.
- This ensures a seamless experience when managing multiple apps.

### Dynamic UI Updates

- **WaitingView**: Automatically updates to display the installation URL and repository checklist for the selected app.
- **Modals**: All modals (e.g., Assign App, App Details) dynamically re-render based on state changes, ensuring the latest data is always displayed.

### Benefits

- **Real-Time Feedback**: Changes to app selection or repository assignments are immediately reflected in the UI.
- **Improved Usability**: The dynamic updates reduce the need for manual refreshes, providing a smoother user experience.

For more details, refer to the [state management documentation](docs/setup.md).

## For Developers: Project Structure & Contribution

The project is built on a modern, modular JavaScript architecture that separates concerns for scalability and maintainability.

### Contribution Guidelines

- **Services (`/services`):** All business logic (API calls, data manipulation) should reside in services. UI components should call these services and not contain business logic themselves.
- **UI Components (`/ui/components`):** Components should be "dumb" and act as pure functions that render HTML based on the data they are given. They should be organized by type (e.g., modals, tables).
- **State (`/state/store.js`):** All application state must be managed through the central store. Do not manage state directly within components or services.
- **UI Controller (`/ui/setup.js`):** This file is the main controller for the UI. It listens for state changes, renders the appropriate components, and binds events to service calls.

For more information, visit the official documentation.
