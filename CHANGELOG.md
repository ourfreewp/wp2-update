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
- Initial release of the WP2 Update plugin.
