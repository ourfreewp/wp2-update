# WP2 Update Plugin

## Overview
The WP2 Update plugin is a WordPress plugin designed to manage updates for GitHub Apps, including fetching repository and release data, handling webhook notifications, and managing app credentials securely.

## Features
- **GitHub Integration**: Create and manage GitHub Apps directly from the WordPress admin interface.
- **Secure Credential Management**: Encrypt and store sensitive data such as API keys and private keys.
- **Custom REST API Endpoints**: Manage apps, packages, and connection settings via a robust REST API.
- **State Management**: Modern JavaScript-driven admin interface with real-time updates.

## Installation
1. Download the plugin files and upload them to the `/wp-content/plugins/wp2-update` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Follow the setup wizard to configure your GitHub App credentials.

## Setup
### Prerequisites
- A GitHub account with permissions to create GitHub Apps.
- WordPress version 5.8 or higher.
- PHP version 7.4 or higher.

### Configuration
1. Navigate to the WP2 Update settings page in the WordPress admin dashboard.
2. Enter your GitHub App credentials, including the private key and webhook secret.
3. Save the settings and verify the connection.

## Development
### Local Development
1. Clone the repository:
   ```bash
   git clone https://github.com/your-repo/wp2-update.git
   ```
2. Install dependencies:
   ```bash
   composer install
   npm install
   ```
3. Build the assets:
   ```bash
   npm run build
   ```
4. Start the development server:
   ```bash
   npm run start
   ```

### Testing
Run the test suite:
```bash
composer test
```

## Contributing
We welcome contributions! Please read our [contributing guidelines](CONTRIBUTING.md) for more details.

## License
This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.
