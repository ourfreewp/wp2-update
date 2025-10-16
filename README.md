# WP2 Update

WP2 Update is a modern, robust, and secure solution for managing updates for WordPress plugins and themes hosted in private or public GitHub repositories. It bridges the gap between your Git-based development workflow and the WordPress update mechanism, providing a seamless, secure, and developer-friendly experience.

By leveraging a direct integration with GitHub Apps, the plugin ensures that updates are delivered efficiently and securely without relying on third-party services or exposing sensitive credentials.

## Features

This plugin is built with a focus on security, performance, and a streamlined user experience.

### 🔐 Secure GitHub App Integration

- **Manifest-Driven Setup:** A "Magic Setup" wizard generates a manifest to create and configure your GitHub App in seconds, automating the entire process.
- **Automated Key Management:** Webhook secrets are generated and managed within WordPress, reducing manual setup and improving security.
- **Credential Security:** Private keys are encrypted at rest, and all communication with the GitHub API is authenticated with short-lived JSON Web Tokens (JWTs).

### 📦 Centralized Package Management

- **Unified Dashboard:** Manage updates for all your GitHub-hosted plugins and themes from a single, intuitive interface.
- **Private & Public Repositories:** Seamlessly handles updates from both private and public GitHub repositories.
- **Automatic Discovery:** The plugin automatically detects installed plugins and themes that contain an Update URI header, making them available for management.

### ⚡ Webhook-Driven Instant Updates

- **Real-Time Notifications:** Utilizes GitHub webhooks for the release event to instantly notify your WordPress site when a new version is published.
- **Efficient Performance:** By clearing update caches only when a new release is available, the plugin avoids slow, repetitive checks against the GitHub API.
- **HMAC-SHA256 Validation:** All incoming webhook payloads are cryptographically verified to ensure they originate from GitHub, preventing unauthorized update triggers.

### 🔄 Polling Service

- **Automated Updates**: The Polling Service periodically checks for new releases on GitHub and updates the package status in WordPress.
- **Configurable Intervals**: Users can adjust the polling frequency in the plugin settings.
- **Error Logging**: All polling activities are logged for transparency and debugging.

### ⚙️ Powerful Version Control

- **One-Click Updates:** Update any managed plugin or theme to the latest version directly from the dashboard.
- **Instant Rollbacks:** Easily roll back a package to any previously published GitHub release with full logging and audit trails.
- **Release Notes:** View full Markdown-formatted release notes from GitHub within the WordPress admin to understand what's new in each version.

### 🛠️ Enhanced App Management

- **AppDTO Class**: Simplifies app-related data handling by providing a structured format for transferring data between the backend and frontend.
- **Improved App Creation Flows**: The plugin now supports both manual and magic setup flows for GitHub Apps, ensuring flexibility and ease of use.

### 🩺 Health & Logging

- **System Health Dashboard:** A dedicated health screen runs a suite of checks on your environment, connectivity, and data integrity to proactively identify issues.
- **Live Log Stream:** A real-time log stream provides immediate insight into all plugin activities, including API calls, webhook events, and security checks, making troubleshooting simple and effective.

### 🖥️ Developer-Friendly Tools

- **Full WP-CLI Support:** Manage all core functions from the command line, including syncing packages, triggering updates, and running health checks.
- **Package Creation Wizard:** A guided wizard to create a new plugin or theme from a starter template and provision it in a new GitHub repository, fully managed and ready to go.

### 🛡️ Robust Security Model

- **Role-Based Access Control:** All actions and REST API endpoints require the `manage_options` capability, ensuring only authorized administrators can manage updates.
- **Nonce Protection:** All actions initiated from the admin interface are protected by WordPress nonces to prevent Cross-Site Request Forgery (CSRF) attacks.
- **Input Sanitization & Output Escaping:** Adheres to WordPress coding standards for sanitizing all inputs and escaping all outputs to prevent Cross-Site Scripting (XSS) and other injection vulnerabilities.

## Requirements

To use WP2 Update, your environment must meet the following requirements:

### Server

- PHP 8.0+
- WordPress 6.0+

### GitHub

- A GitHub account (personal or organization)
- Administrative permissions to create and manage a GitHub App

### Managed Plugins/Themes

For a plugin or theme to be discoverable by WP2 Update, its main file must contain an Update URI header pointing to the GitHub repository slug.

**Example:**
```
Update URI: your-github-username/your-repository-name
```

## Installation & Configuration

1. **Install the Plugin:** Download the latest release ZIP file. In your WordPress admin, navigate to Plugins > Add New, click Upload Plugin, and activate it.
2. **Launch the Setup Wizard:** Navigate to the new WP2 Update menu. You will be prompted to connect your first GitHub App.
3. **Create the GitHub App:** Follow the on-screen "Magic Setup" instructions. The wizard will generate a manifest and provide a link to create the app on GitHub automatically.
4. **Install the App:** After creating the app, GitHub will prompt you to install it. Grant it access to the repositories you wish to manage.
5. **Done!** Once the app is installed, you will be redirected back to WordPress, and the plugin will automatically detect the connection. Your manageable plugins and themes will now appear in the dashboard.

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
  ```
  https://your-site.com/wp-json/wp2-update/v1/webhook
  ```
- **Events:**  
  The plugin listens for the `release` event with the `published` action.

## License

This plugin is licensed under the [GPL-2.0+](LICENSE) license. See the LICENSE file for more details.
