# WP2 Update

A modern, robust, and secure solution for managing updates for WordPress plugins and themes hosted in private or public GitHub repositories.

WP2 Update bridges the gap between your Git-based development workflow and the WordPress update mechanism. By leveraging GitHub Apps, it provides a secure and efficient way to deliver updates to your WordPress sites without relying on third-party services or cumbersome manual processes.

## Features

- **Secure GitHub App Integration**  
  Connects to your GitHub account using a dedicated GitHub App, ensuring that your credentials are never exposed.

- **Private and Public Repository Support**  
  Manage updates for plugins and themes hosted in both private and public repositories.

- **Hybrid SPA Admin Interface**  
  A fast, modern, and responsive admin dashboard built with a JavaScript-driven interface that provides real-time feedback and communicates with a robust REST API.

- **Real-Time Logging and Health Check**  
  A dedicated Health tab with a live log stream and detailed system checks to monitor performance and troubleshoot issues instantly.

- **Webhook-Driven Updates**  
  Utilizes GitHub webhooks to automatically trigger and synchronize update checks when a new release is published, ensuring that updates are available instantly without manual intervention.

- **Centralized Package Management**  
  A unified dashboard to view the status of all your GitHub-hosted plugins and themes, including the currently installed version, the latest available version, and the managing GitHub App.

- **Instant Rollback and Version Control**  
  The ability to roll back to any previous available version of a plugin or theme directly from the WordPress admin interface with full logging and audit trails.

- **Command Line Interface (CLI)**  
  Full support for WP-CLI, allowing developers to manage synchronization, perform updates, and trigger rollbacks directly from the command line.

- **Package Creation Wizard**  
  A guided, multi-step wizard to create new template-based plugins or themes directly into a managed GitHub repository.

## Requirements

- PHP 8.0+
- WordPress 6.0+
- A GitHub account

## Installation

1. Download the latest release of the plugin from the [releases page](https://github.com/ourfreewp/wp2-update/releases).
2. In your WordPress admin, go to **Plugins > Add New** and click the **Upload Plugin** button.
3. Upload the downloaded ZIP file and activate the plugin.

## Configuration

To start using WP2 Update, you need to connect it to a GitHub App.

1. In your WordPress admin, navigate to the **WP2 Updates** page.
2. Click the **Add GitHub App** button or the "Get Started" link to open the setup wizard.
3. Follow the multi-step, on-screen instructions to generate a manifest and create a new GitHub App in your GitHub account.
4. Install the newly created app on the repositories you want to manage.
5. Once the app is installed, the plugin will automatically detect the connection, and your repositories will appear in the dashboard.

## Development

The plugin is built with a modern development stack, including [Vite](https://vitejs.dev/) for front-end asset bundling and [Composer](https://getcomposer.org/) for managing PHP dependencies.

### Prerequisites

- [Node.js](https://nodejs.org/) (v18 or higher)
- [Composer](https://getcomposer.org/)

### Setup

Clone the repository:

```sh
git clone https://github.com/ourfreewp/wp2-update.git
```

Install PHP dependencies:

```sh
composer install
```

Install JavaScript dependencies:

```sh
npm install
```

### Build Process

- **Development:** Start the Vite development server with hot-reloading:

  ```sh
  npm run dev
  ```

- **Production:** Build the optimized assets for production:

  ```sh
  npm run build
  ```

## Coding Standards

- **PHP:** The plugin follows the WordPress Coding Standards. Check for compliance:

  ```sh
  composer run lint
  ```

- **JavaScript:** Uses ESLint with the recommended WordPress configuration. Check for compliance:

  ```sh
  npm run lint
  ```

## Webhooks

The plugin relies on webhooks from GitHub to automatically check for new releases. When you create your GitHub App, the necessary webhook endpoint is automatically configured.

- **Endpoint:**  
  `https://your-site.com/wp-json/wp2-update/v1/webhook`

- **Events:**  
  The plugin listens for the `release` event with the `published` action.

## License

This plugin is licensed under the [GPL-2.0+](LICENSE) license. See the LICENSE file for more details.