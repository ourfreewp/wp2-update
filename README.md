# WP2 Update

A modern, robust, and secure solution for managing updates for WordPress plugins and themes hosted in private or public GitHub repositories.

WP2 Update bridges the gap between your Git-based development workflow and the WordPress update mechanism. By leveraging GitHub Apps, it provides a secure and efficient way to deliver updates to your WordPress sites without relying on third-party services or cumbersome manual processes.

## Features

  * **Secure GitHub App Integration**: Connects to your GitHub account using a dedicated GitHub App, ensuring that your credentials are never exposed.
  * **Private and Public Repository Support**: Manage updates for plugins and themes hosted in both private and public repositories.
  * **Hybrid SPA Admin Interface**: A fast, modern, and responsive admin dashboard built with a JavaScript-driven interface that communicates with a robust back-end REST API.
  * **Webhook-Driven Updates**: Utilizes GitHub webhooks to automatically trigger update checks when a new release is published, ensuring that updates are available almost instantly.
  * **Centralized Package Management**: A unified dashboard to view the status of all your GitHub-hosted plugins and themes, including the currently installed version, the latest available version, and the managing GitHub App.
  * **Rollback and Version Control**: The ability to roll back to a previous version of a plugin or theme if an update causes issues, directly from the WordPress admin.
  * **Detailed Logging and Debugging**: A dedicated logging interface in the admin dashboard for troubleshooting and monitoring the plugin's activity.

## Requirements

  * PHP 8.0+
  * WordPress 6.0+
  * A GitHub account

## Installation

1.  Download the latest release of the plugin from the [releases page](https://www.google.com/search?q=https://github.com/ourfreewp/wp2-update/releases).
2.  In your WordPress admin, go to **Plugins \> Add New** and click the **Upload Plugin** button.
3.  Upload the downloaded ZIP file and activate the plugin.

## Configuration

To start using WP2 Update, you need to connect it to a GitHub App.

1.  In your WordPress admin, navigate to the **WP2 Updates** page.
2.  Click the **Add GitHub App** button to open the setup wizard.
3.  Follow the on-screen instructions to generate a manifest and create a new GitHub App in your GitHub account.
4.  Install the newly created app on the repositories you want to manage.
5.  Once the app is installed, the plugin will automatically detect the connection, and your repositories will appear in the dashboard.

## Development

The plugin is built with a modern development stack, including Vite for front-end asset bundling and Composer for managing PHP dependencies.

### Prerequisites

  * [Node.js](https://nodejs.org/) (v18 or higher)
  * [Composer](https://getcomposer.org/)

### Setup

1.  Clone the repository:
    ```sh
    git clone https://github.com/ourfreewp/wp2-update.git
    ```
2.  Install PHP dependencies:
    ```sh
    composer install
    ```
3.  Install JavaScript dependencies:
    ```sh
    npm install
    ```

### Build Process

  * **Development**: Run the following command to start the Vite development server with hot-reloading:
    ```sh
    npm run dev
    ```
  * **Production**: To build the optimized assets for production, run:
    ```sh
    npm run build
    ```

### Coding Standards

  * **PHP**: The plugin follows the WordPress Coding Standards. You can check for compliance by running:
    ```sh
    composer run lint
    ```
  * **JavaScript**: We use ESLint with the recommended WordPress configuration. To check for compliance, run:
    ```sh
    npm run lint
    ```

## Webhooks

The plugin relies on webhooks from GitHub to automatically check for new releases. When you create your GitHub App, the necessary webhook endpoint is automatically configured.

  * **Endpoint**: `https://your-site.com/wp-json/wp2-update/v1/webhook`
  * **Events**: The plugin listens for the `release` event with the `published` action.

## Contributing

We welcome contributions of all kinds, from bug reports to new features. Please see our `CONTRIBUTING.md` file for detailed guidelines on how to contribute to the project.

## License

This plugin is licensed under the GPL-2.0+ license. See the `LICENSE` file for more details.