# WP2 Update: Private Theme & Plugin Updater for GitHub

A modern, secure, and reliable solution for managing private WordPress theme and plugin updates directly from your GitHub repositories.

WP2 Update leverages the power and security of GitHub Apps to provide a seamless and automated update workflow, replacing outdated methods that rely on personal access tokens. With a reactive admin interface powered by Vite and a robust PHP backend, managing your private packages has never been easier.

## ✨ Features

- **Secure GitHub App Integration**  
  Connects to your repositories using the modern GitHub App authentication model, ensuring your personal tokens are never exposed.

- **Multi-App Support**  
  Manage multiple distinct GitHub Apps from a single WordPress instance, allowing you to organize your updates by client, project, or organization.

- **Automatic Update Detection**  
  Periodically scans your managed themes and plugins and notifies you of new releases directly on the WordPress Updates page.

- **Centralized Package Management**  
  A beautiful admin dashboard provides an at-a-glance overview of all your managed themes and plugins, their current versions, and update status.

- **Detailed Package View**  
  Dive into any package to view its status, complete version history, and an event log.

- **One-Click Rollbacks & Upgrades**  
  Easily install any previous or current version of a theme or plugin directly from the Version History tab.

- **Bulk Actions**  
  Force update checks or clear the cache for multiple packages at once to save time.

- **System Health Dashboard**  
  A comprehensive health page provides detailed debugging information about your WordPress environment and connection status for each configured GitHub App.

- **Modern Tech Stack**  
  The admin interface is built with Vite, utilizing ES Modules, Nano Stores for state management, and Toastify.js for non-blocking notifications.

## 🚀 Getting Started

Follow these three steps to configure the plugin and start managing your private repositories.

### Step 1: Mark Your Packages for Management

To make a theme or plugin manageable by WP2 Update, you must add the `Update URI` header to its main file. The value should be the GitHub repository slug in `owner/repository` format.

**For a Theme (`style.css`):**

```css
/*
Theme Name: My Awesome Theme
Theme URI: https://example.com/my-awesome-theme
Author: Your Name
Version: 1.2.5
Update URI: your-github-username/my-awesome-theme-repo
*/
```

**For a Plugin (`my-plugin.php`):**

```php
<?php
/*
Plugin Name: My Awesome Plugin
Plugin URI: https://example.com/my-awesome-plugin
Author: Your Name
Version: 2.1.0
Update URI: your-github-username/my-awesome-plugin-repo
*/
```

Once you add this header, the plugin will automatically appear in the Managed Packages list.

### Step 2: Create and Configure a GitHub App

1. Navigate to **GitHub Settings > Developer settings > GitHub Apps** and click **New GitHub App**.
2. Fill out the registration form:
   - **GitHub App name:** A descriptive name, e.g., "My WordPress Updater".
   - **Homepage URL:** The URL of your WordPress site (e.g., `https://your-site.com`).
   - **Callback URL:** Uncheck "Active". This is not needed.
3. **Webhook:**
   - Check "Active".
   - **Webhook URL:** Find this on the WP2 Update > Settings page in your WordPress admin. It will look like `https://your-site.com/wp-json/wp2-update/v1/github/webhooks`.
   - **Webhook secret:** Generate a secure secret and save it. You will need this for the plugin settings.
4. **Repository permissions:**
   - **Contents:** Read-only (Required to read repository metadata and download release assets).
   - **Metadata:** Read-only (Required by default).
5. Click **Create GitHub App**.
6. On the next page, under **Private keys**, click **Generate a private key**. A `.pem` file will be downloaded. Keep this file secure.
7. Go to the **Install App** tab and install the app on your account, granting it access to the specific repositories you want to manage. After installation, you will see an Installation ID in the URL (e.g., `.../installations/12345678`). You will need this ID.

### Step 3: Configure the Plugin in WordPress

1. Navigate to **WP2 Updates > Settings** in your WordPress admin dashboard.
2. Fill in the following fields:
   - **App ID:** Found on your GitHub App's general settings page.
   - **Installation ID:** The ID you noted after installing the app on your repositories.
   - **Private Key:** Upload the `.pem` file you downloaded from GitHub.
   - **Webhook Secret:** The secret you created during the GitHub App configuration.
3. Click **Save Settings**.
4. Use the **Test Connection** button to verify that everything is configured correctly. You should see a success toast notification.

## 🔧 Development

This plugin uses a modern Vite-powered toolchain for JavaScript development.

**Install Dependencies:**

```sh
npm install
composer install
```

**Run the Development Server:**  
This command starts the Vite dev server with Hot Module Replacement (HMR) for a seamless development experience.

```sh
npm run dev
```

**Build for Production:**  
This command bundles and minifies all JavaScript and CSS assets into the `assets/dist/` directory, ready for deployment.

```sh
npm run build
```

## 📄 License

This plugin is licensed under the GPLv2 or later.
