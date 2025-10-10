# WP2 Update Plugin

WP2 Update is a WordPress plugin designed to simplify the management of GitHub repositories and packages directly from your WordPress dashboard. With this plugin, you can connect your GitHub account, manage repositories, and install packages seamlessly.

## Features
- Connect your WordPress site to GitHub using a GitHub App.
- Manage private and public repositories.
- Install and update packages directly from the WordPress dashboard.
- Secure and user-friendly interface.

---

## Installation and Setup Guide

### Prerequisites
1. A GitHub account.
2. Administrator access to your WordPress site.

### Step 1: Create a GitHub App
1. Log in to your GitHub account.
2. Navigate to **Settings > Developer Settings > GitHub Apps**.
3. Click **New GitHub App**.
4. Fill in the required fields:
   - **GitHub App Name**: Choose a name for your app.
   - **Homepage URL**: Enter your WordPress site URL.
   - **Callback URL**: Use the URL provided in the plugin settings.
   - **Permissions**: Grant the necessary permissions for repositories, metadata, and webhooks.
5. Save the app and generate a private key.
6. Copy the App ID, Client ID, and Client Secret.

### Step 2: Configure the Plugin
1. Install and activate the WP2 Update plugin.
2. Navigate to **Settings > WP2 Update** in your WordPress dashboard.
3. Enter the GitHub App credentials (App ID, Client ID, Client Secret, and Private Key).
4. Save the settings and follow the on-screen instructions to complete the setup.

---

## Usage Instructions

### Managing Repositories
1. Navigate to **WP2 Update > Repositories**.
2. View and manage your connected repositories.
3. Install or update packages directly from the dashboard.

### Updating Packages
1. Go to **WP2 Update > Updates**.
2. Select the packages you want to update.
3. Click **Update Selected** to apply updates.

---

## FAQ

### What permissions does the GitHub App require?
The app requires permissions for repositories, metadata, and webhooks to function correctly.

### How do I troubleshoot connection issues?
1. Verify your GitHub App credentials.
2. Check your server's connectivity to GitHub.
3. Review the plugin logs for detailed error messages.

### Can I use this plugin with multiple GitHub accounts?
Currently, the plugin supports one GitHub account per WordPress site.

---

For more information, visit the [official documentation](https://github.com/ourfreewp/wp2-update).
