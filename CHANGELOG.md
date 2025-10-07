# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]
### Added
- Nonce verification in `handle_update_check` to prevent CSRF vulnerabilities.
- PHPDoc blocks for improved code documentation.
- Refactored `is_github_ip` to dynamically fetch GitHub IP ranges from the `/meta` API.

### Changed
- Refactored method names in `WP2UpdateCommand` to use `camelCase` for consistency.
- Updated `.distignore` to include `vendor/` and `dist/` directories in the build.
- Updated `README.md` to remove references to the deprecated backup feature.

### Removed
- Deprecated backup feature, including `BackupManagementPage` and `BackupEndpoints`.

### Fixed
- Sanitized superglobal usage in `SystemHealthPage` to enhance security.
- Updated permission callback for webhook endpoint to `__return_true` with HMAC validation.

## [1.0.0] - 2025-10-07

### Added
- Initial public release.
- Core functionality for managing private theme and plugin updates via GitHub Apps.
- Admin UI for viewing managed packages, system health, and connection status.
- WP-CLI commands for `sync`, `health`, `list-updates`, and `update`.
- Integration with Action Scheduler for background repository syncing and health checks.

### Fixed
- **Data Integrity:** Package caches are now properly cleared when themes or plugins are installed, activated, or deactivated.
- **Admin UI:** Resolved issue where manual sync from the System Health page would fail due to an invalid nonce.
- **Coding Standards:** Ensured all user-facing strings are correctly escaped for security.

### Security
- **CRITICAL:** Added nonce verification to all admin form submissions and actions to prevent Cross-Site Request Forgery (CSRF) attacks.
- **CRITICAL:** Hardened webhook endpoint by improving IP address validation logic to correctly identify requests originating from GitHub.
- **CRITICAL:** Replaced insecure file handling function (`file_get_contents`) with the WordPress standard `wp_read_file` for processing uploaded private keys.
- **Hardening:** Enforced strict `manage_options` capability checks on all administrative REST API endpoints.
