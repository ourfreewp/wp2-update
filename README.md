# WP2 Update: Private Theme & Plugin Updater for GitHub

A modern, secure, and reliable solution for managing private WordPress theme and plugin updates directly from your GitHub repositories.

WP2 Update leverages the power and security of GitHub Apps to provide a seamless and automated update workflow, replacing outdated methods that rely on personal access tokens. With a reactive admin interface and a robust PHP backend, managing your private packages has never been easier or more secure.

## ✨ Key Features

- **Secure Authentication:** Uses the modern GitHub Apps workflow instead of insecure Personal Access Tokens.
- **Automated Workflow:** Automatically syncs repositories and checks for new releases using background tasks powered by Action Scheduler.
- **Webhook Integration:** Listens for GitHub webhook events to trigger instant update checks when you publish a new release.
- **Pre-Update Backups & Management:** Automatically creates a `.zip` backup of your theme or plugin before an update is installed. Manage, download, or restore backups from a dedicated UI.
- **Full WP-CLI Integration:** Automate and manage every aspect of the plugin from the command line, from syncing repos to running updates.
- **Modern Admin UI:** A clean, intuitive interface for viewing managed packages, checking system health, and browsing version history.
- **Comprehensive Logging:** A detailed event log tracks every action, from API calls to update installations, for easy debugging.
- **Developer Friendly:** Extensible with a rich set of WordPress actions and filters for custom integrations.
- **Granular Permissions:** Uses a custom WordPress capability (`manage_wp2_updates`) to allow fine-grained access control.

## 📋 Requirements

- PHP 8.0 or higher
- WordPress 6.0 or higher
- Composer for dependency management

## 🚀 Installation & Setup

### Step 1: Install the Plugin

1. Clone this repository into your `wp-content/plugins` directory.
2. Navigate to the plugin directory and run `composer install` to install the required dependencies.
3. Activate the "WP2 Update" plugin through the WordPress admin dashboard.

### Step 2: Create a GitHub App

This is the most critical step. You need to create a dedicated GitHub App to handle the authentication.

#### Navigate to GitHub Developer Settings

1. Go to your GitHub profile settings.
2. Select **Developer settings** from the left-hand menu.
3. Click on **GitHub Apps** and then **New GitHub App**.

#### Fill in the App Details

- **GitHub App name:** Give it a descriptive name (e.g., "My Site's Updater").
- **Homepage URL:** Enter your WordPress site's home URL (e.g., `https://example.com`).
- **Webhook URL:** Enter your site's webhook endpoint:  
	`https://example.com/wp-json/wp2-update/v1/github/webhooks`
- **Webhook Secret:** Generate a strong, random string and save it. You will need this later.

#### Set Repository Permissions

- Under the **Repository permissions** section, you only need to grant **Read-only** access to **Contents**. The plugin does not need write access.

#### Subscribe to Events

- Under the **Subscribe to events** section, select the following events:
	- **Release:** Triggered when a new release is published.
	- **Installation repositories:** Triggered when you change which repositories the app can access.

#### Create and Install the App

1. Click **Create GitHub App**.
2. On the next page, generate a private key and download the `.pem` file. Store this securely!
3. Finally, **install** the app on your account and grant it access to the specific repositories you want to manage. Note the Installation ID from the URL (it will be a number).

### Step 3: Configure the Plugin in WordPress

1. Navigate to **WP2 Updates > Settings** in your WordPress admin dashboard.
2. Click **Add New GitHub App**.
3. Fill in the details you collected in the previous step:
		- **App Name:** A friendly name for your reference.
		- **App ID:** The ID from your GitHub App's settings page.
		- **Installation ID:** The ID from when you installed the app on your account.
		- **Private Key:** Open the `.pem` file you downloaded and copy-paste the entire contents into this field.
		- **Webhook Secret:** The secret you generated in Step 2.
4. Save the app. The plugin will automatically perform a health check to ensure the credentials are valid.

### Step 4: Mark Your Packages

For the plugin to recognize your themes and plugins, you must add an `Update URI` header to their main file. The value should be the repository slug (`owner/repo-name`).

**Theme Example (`style.css`):**
```css
/*
 * Theme Name: My Private Theme
 * Version: 1.0.0
 * Author: Your Name
 * Update URI: your-github-name/my-private-theme
 */
```

**Plugin Example (`my-plugin.php`):**
```php
<?php
/*
 * Plugin Name: My Private Plugin
 * Version: 1.0.0
 * Author: Your Name
 * Update URI: your-github-name/my-private-plugin
 */
```

The plugin will now automatically discover and manage updates for these packages.

## ⚙️ WP-CLI Commands

WP2 Update includes a full suite of WP-CLI commands for headless management and automation.

- `wp wp2-update sync`: Triggers a synchronization of all repositories from all connected GitHub Apps.
	- `--app-slug=<slug>`: Sync repositories for a specific app only.
- `wp wp2-update health`: Runs a health check on all apps and repositories.
	- `--app-slug=<slug>`: Run a health check for a specific app.
	- `--repo-slug=<slug>`: Run a health check for a specific repository.
- `wp wp2-update list`: Lists all managed packages with their current and latest versions.
- `wp wp2-update update <package-key>`: Updates a specific package to its latest version.

> The `<package-key>` is in the format `theme:slug` or `plugin:slug/plugin-file.php`.

## 🤝 Contributing

Contributions are welcome! Please read our [docs/wiki/Contributing.md](docs/wiki/Contributing.md) file for details on our code standards and the process for submitting pull requests.

## 📄 License

This project is licensed under the GPLv2 or later – see the `wp2-update.php` file for details.
