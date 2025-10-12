# AI Coding Agent Instructions for WP2 Update

Welcome to the WP2 Update codebase! This document provides essential guidelines for AI coding agents to be productive and effective contributors to this project.

## Project Overview

WP2 Update is a WordPress plugin designed to manage updates for plugins and themes hosted in GitHub repositories. It features:
- **Hybrid SPA Admin Interface**: A JavaScript-driven admin dashboard communicating with a REST API.
- **Webhook-Driven Updates**: GitHub webhooks trigger update checks.
- **Centralized Package Management**: Unified dashboard for managing GitHub-hosted plugins and themes.
- **Detailed Logging**: Logs for debugging and monitoring plugin activity.

## Key Components

### 1. **Back-End (PHP)**
- **REST API**: Located in `src/REST/`. Controllers like `HealthController` handle API endpoints.
- **Admin Screens**: Managed in `src/Admin/Screens/`. The `Manager.php` file renders the admin dashboard.
- **Core Services**: Found in `src/Core/`. These include `ConnectionService`, `CredentialService`, and `ReleaseService`.
- **Logging**: Implemented in `src/Utils/Logger.php`. Logs are stored in the database table `wp2_update_logs`.

### 2. **Front-End (JavaScript)**
- **Modules**: JavaScript modules are in `assets/scripts/modules/`.
- **Views**: UI components like `HealthView.js` are in `assets/scripts/modules/ui/views/`.
- **State Management**: Centralized state is managed in `assets/scripts/modules/state/store.js`.

### 3. **Database**
- The plugin creates and manages the `wp2_update_logs` table for logging.
- Database interactions use the WordPress `$wpdb` object.

### 4. **Webhooks**
- GitHub webhooks trigger update checks. The endpoint is `/wp-json/wp2-update/v1/webhook`.

## Development Workflow

### Setup
1. Clone the repository: `git clone https://github.com/ourfreewp/wp2-update.git`
2. Install PHP dependencies: `composer install`
3. Install JavaScript dependencies: `npm install`

### Build
- **Development**: `npm run dev` (Vite development server with hot-reloading)
- **Production**: `npm run build` (Optimized assets)

### Testing
- **PHP Unit Tests**: Located in `tests/`. Run with:
  ```sh
  composer run test
  ```
- **JavaScript Linting**: Check compliance with:
  ```sh
  npm run lint
  ```

## Project-Specific Conventions

1. **Hybrid Rendering**: Admin views are server-rendered (PHP) and hydrated with JavaScript for interactivity.
2. **REST API Design**: Follow WordPress REST API conventions. Use `WP_REST_Request` and `WP_REST_Response`.
3. **Logging**: Use `Logger::log()` for consistent logging. Ensure the `wp2_update_logs` table exists before writing logs.
4. **Coding Standards**:
   - PHP: WordPress Coding Standards (`composer run lint`)
   - JavaScript: ESLint with WordPress configuration (`npm run lint`)

## Integration Points

- **GitHub API**: Interactions are managed via `CredentialService` and `ConnectionService`.
- **Database**: Use `$wpdb` for queries. Ensure tables are created during plugin activation.
- **Admin UI**: Extend `Manager.php` for new tabs or panels. Use JavaScript views for dynamic content.

## Examples

### Adding a New REST Endpoint
1. Create a new controller in `src/REST/Controllers/`.
2. Register the route in `register_routes()`.
3. Implement the callback method using `WP_REST_Request` and return a `WP_REST_Response`.

### Adding a New Admin Tab
1. Update `src/Admin/Screens/Manager.php` to include the tab in the navigation.
2. Create a corresponding view in `assets/scripts/modules/ui/views/`.
3. Hydrate the view with JavaScript in `admin-main.js`.

---

For more details, refer to the `README.md` and `CONTRIBUTING.md` files. If you encounter any issues, consult the logs or the `troubleshooting.md` file in the `docs/` directory.