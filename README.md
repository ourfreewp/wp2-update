
# WP2 Update


WP2 Update is a modular MU-plugin for secure, automated updates of private WordPress themes, plugins, and daemons hosted on GitHub. It uses a GitHub App for authentication and integrates with WordPress’ native update system.

**Admin UI:**
- Health checks for all package types
- View available versions/releases
- Install, reinstall, or roll back releases
- Recent updater log events
- Manage plugins, themes, and daemons

---


## Features

- Detects managed themes, plugins, and daemons via `UpdateURI: owner/repo`.
- Concurrent GitHub API requests (fast updates for multiple packages).
- Update prompts shown in native WordPress UI and custom admin UI.
- Install/reinstall/rollback directly from GitHub releases.
- Supports private repositories via GitHub App authentication (no PAT required).
- **Admin UI** for all package types:
	- Health Check: connectivity, rate-limit status, update availability.
	- Available Versions: latest releases, changelog snippets, actions.
	- Recent Events: last 10 logged actions/errors.
- **Secure:**
	- Capability + nonce checks on all actions.
	- Secrets redacted in logs.
	- Escaping all output.

---


## Installation


### 1. Copy the plugin folder into `wp-content/mu-plugins/`.

### 2. Create and configure a GitHub App:

- Go to **Settings → Developer settings → GitHub Apps** on GitHub.
- Create a new app with **Repository Contents: Read-only** permission.
- Install the app on the repositories you want to manage.
- Download the private key (`.pem` file).

### 3. Configure `wp-config.php`:

Add the following constants (replace with your values):

```php
define( 'WP2_GITHUB_APP_ID', 'YOUR_APP_ID' );
define( 'WP2_GITHUB_INSTALLATION_ID', 'YOUR_INSTALLATION_ID' );
define( 'WP2_GITHUB_PRIVATE_KEY_PATH', '/absolute/path/to/your/private-key.pem' );
```

### 4. Add an `UpdateURI` header to your theme’s `style.css`, plugin header, or daemon header:

	For themes:
	```css
	/*
	Theme Name: My Custom Theme
	UpdateURI: your-org/your-repo
	*/
	```
	For plugins/daemons:
	```php
	/*
	Plugin Name: My Custom Plugin
	UpdateURI: your-org/your-repo
	*/
	```

4. Visit **Tools → WP2 Update** to manage updates for all package types.

---

## Requirements

- WordPress 5.8+
- PHP 7.4+
- GitHub App (with repository access) for private repos

---


## Architecture

WP2 Update is structured for modularity and extensibility:

- **Core**: Interfaces and contracts for packages and admin UIs.
- **Packages**: Implementations for Themes, Plugins, Daemons (easy to add more).
- **Helpers**: Shared utilities for API calls, output escaping, etc. (reduces code duplication).
- **Utils**: Logging and other utilities.
- **Init**: Orchestrates loading and registration of all package types.

---


## Extending

To add support for a new package type:

1. Create a directory under `src/Packages/{Type}`.
2. Implement `Discovery`, `Init`, and `Admin` classes in that directory, following the Core interfaces.
3. Use helpers from `src/Helpers` to avoid code duplication (e.g., for API calls).
4. Register your new package in the main `Init` orchestrator.
5. This structure allows easy addition of new package types while maintaining consistency and leveraging shared infrastructure.

---


## Future Work

- Generalize Admin layer for all package types (reduce duplication, more helpers).
- Async health checks via AJAX/REST for faster admin pages.
- Bulk actions for multiple themes/plugins/daemons.
- Improved semantic versioning and prerelease handling.
- Multisite/network admin support.
- Enhanced logs (filtering, export, metrics).
- GitHub App authentication support.
- Richer release UI (changelogs, diffs).
- Developer hooks for post-install events.
- Unit and integration tests with mocked GitHub API.
