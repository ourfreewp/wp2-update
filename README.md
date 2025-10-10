# WP2 Update Plugin

## Overview
The WP2 Update Plugin is a WordPress plugin designed to streamline the management of plugin and theme updates via GitHub. It provides a user-friendly interface for validating connections, syncing packages, and managing updates.

## Features
- **Validation Flow**: Validate GitHub App credentials and connection.
- **Sync Packages**: Fetch and display available packages and their releases.
- **Update Management**: Update or rollback plugins and themes to specific versions.
- **Webhook Integration**: Automatically clear update transients on GitHub push events.
- **UI Enhancements**: Loading skeletons, empty states, and confirmation modals for better user experience.

## Installation
1. Download the plugin ZIP file from the [releases page](https://github.com/ourfreewp/wp2-update/releases).
2. Log in to your WordPress admin dashboard.
3. Navigate to `Plugins > Add New`.
4. Click `Upload Plugin` and select the downloaded ZIP file.
5. Click `Install Now` and activate the plugin.

## Setup
1. Navigate to `WP2 Updates` in the WordPress admin menu.
2. Enter your GitHub App credentials:
   - App Name
   - App ID
   - Installation ID
   - Private Key
3. Save the credentials and validate the connection.
4. Sync packages to fetch available plugins and themes.

## Usage
- **Validate Connection**: Ensure your GitHub App credentials are correct.
- **Sync Packages**: Fetch the latest packages and their releases.
- **Manage Updates**: Select a version and update or rollback packages.
- **Webhooks**: Automatically clear update transients on GitHub push events.

## New Features
- **State-Driven Dashboard**: A centralized dashboard that dynamically updates based on connection and package states.
- **Installation Status Endpoint**: A dedicated endpoint to check the installation status of the GitHub App.
- **Rollback Functionality**: Easily revert plugins and themes to previous versions.
- **Enhanced Error Handling**: Improved user-facing error messages and detailed backend logging for better debugging.

## Updated Usage
- **State-Driven Dashboard**: Navigate through the dashboard to manage connections, sync packages, and handle updates.
- **Installation Status Check**: The dashboard automatically polls the installation status endpoint to ensure the GitHub App is installed.

## Screenshots
### Admin Dashboard
![Admin Dashboard](https://example.com/screenshot1.png)

### Package Management
![Package Management](https://example.com/screenshot2.png)

## FAQ
### What permissions are required for the GitHub App?
The GitHub App requires the following permissions:
- **Contents**: Read-only
- **Metadata**: Read-only

### How do I generate a private key for the GitHub App?
1. Go to your GitHub App settings.
2. Click `Generate a private key`.
3. Download the key and upload it in the plugin settings.

### What happens if validation fails?
Ensure your App ID, Installation ID, and Private Key are correct. Revalidate after correcting any errors.

### How do I rollback a package?
Navigate to the `WP2 Updates` menu, select the package, and choose the rollback option. The plugin will automatically fetch and install the previous version.

### What happens if an update fails?
The plugin provides detailed error messages and logs to help you identify and resolve the issue. Check the WordPress debug log for more information.

## Contributing
We welcome contributions! Please see the [Contributing Guide](docs/wiki/Contributing.md) for details.

## License
This plugin is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.

## Changelog
See [CHANGELOG.md](CHANGELOG.md) for version history.
