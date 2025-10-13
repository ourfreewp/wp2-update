# WP2 Update Architecture Overview

This document provides a high-level overview of the internal architecture of the WP2 Update plugin. Understanding this structure is key for developers looking to contribute to the project or extend its functionality.

## Core Directory Structure

The plugin is organized into several key directories under the `/src` folder, each with a distinct responsibility, following PSR-4 autoloading standards.

-   **/src/Core**: Contains the primary business logic of the plugin.
    -   **API**: Handles all communication with the GitHub API, including authentication (`ClientService`, `CredentialService`), connection management (`ConnectionService`), and fetching release data (`ReleaseService`, `RepositoryService`).
    -   **Updates**: Manages the WordPress update process. It finds packages (`PackageFinder`), hooks into the WordPress update transients (`PluginUpdater`, `ThemeUpdater`), and provides a service layer (`PackageService`) for managing package actions.
    -   **AppRepository**: A repository class responsible for persisting and retrieving GitHub App definitions from the WordPress options table.

-   **/src/Admin**: Manages the WordPress admin-facing interface.
    -   **Assets**: Enqueues and manages all CSS and JavaScript assets, including the Vite manifest.
    -   **Menu**: Registers the main admin menu and submenu pages.
    -   **Screens**: Renders the HTML for the admin pages, which serve as the foundation for the JavaScript-driven SPA.
    -   **DashboardData**: Preloads and localizes data from PHP to JavaScript for the initial state of the admin dashboard.

-   **/src/REST**: Defines the plugin's REST API endpoints.
    -   **Controllers**: Contains the controller classes for each REST resource (e.g., `AppsController`, `PackagesController`). They handle the request, call the appropriate services, and formulate the response.
    -   **Router**: Centralizes the registration of all REST API routes.

-   **/src/Webhook**: Contains the controller responsible for handling incoming webhooks from GitHub.

-   **/src/Security**: Manages security-related functionality, such as permission checks and nonce validation.

-   **/src/Utils**: A collection of utility classes for common tasks like logging, caching, and making HTTP requests.

## Component Interaction & The Service Container

The plugin's architecture is designed around a simple dependency injection pattern, with the `Init.php` file at the root of the `src` directory acting as a service container.

During the `plugins_loaded` action, the `Init::boot()` method is called. This instantiates all the necessary service classes (like `CredentialService`, `PackageService`, etc.) and injects their dependencies. This ensures that each component has access to the services it needs to perform its function.

For example, the `PackagesController` receives an instance of the `PackageService` in its constructor. When a REST API request comes in to update a package, the controller can then call the appropriate method on the `PackageService` to handle the business logic.

## Data Flow: From Webhook to Update

The following diagram illustrates the data flow when a new release is published on GitHub, triggering an update in WordPress:

```
+-----------------+      +--------------------+      +----------------------+
|   GitHub Repo   |----->| GitHub Webhook     |----->|   WP2 Update         |
| (New Release)   |      | (release:published)|      |  /wp-json/wp2-update/|
+-----------------+      +--------------------+      |    v1/webhook        |
+----------------------+
|
V
+-------------------------+      +-----------------------------+
|  WordPress Update Check |<-----| Clear Update Transients     |
| (Checks for new versions)|      | (delete_site_transient)     |
+-------------------------+      +-----------------------------+
|
V
+--------------------------+
| User sees update notice  |
| in WordPress Admin       |
+--------------------------+

```

1.  **New Release Published**: A developer publishes a new release for a repository on GitHub.
2.  **Webhook Sent**: GitHub sends a `release` event webhook to the registered endpoint in the WP2 Update plugin.
3.  **Webhook Received**: The `Webhook/Controller.php` class receives the incoming request.
4.  **Signature Validation**: The controller validates the HMAC-SHA256 signature of the request to ensure it genuinely came from GitHub.
5.  **Transients Cleared**: If the signature is valid, the controller deletes the `update_plugins` and `update_themes` WordPress transients.
6.  **WordPress Checks for Updates**: The next time WordPress checks for updates, it finds that the transients are missing and is forced to fetch fresh data.
7.  **Update Data Injected**: During this check, the `PluginUpdater` and `ThemeUpdater` classes in the plugin inject the new version information from your GitHub release into the update data.
8.  **Update Notice Displayed**: The user sees the update notification in their WordPress admin dashboard and can update the plugin or theme as usual.
