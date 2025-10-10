# Changelog

## [1.0.0] - 2025-10-10
### Added
- Finalized error handling for package updates.
- Disabled global actions during updates.
- Added post-action feedback with a "Last Synced" timestamp.
- Improved accessibility for dynamic content.
- Expanded `sync_packages` endpoint data.
- Improved GitHub API error handling.
- Added nonce to `github-callback.js` for secure API calls.
- Secured webhook endpoint with validation and encryption.
- Implemented webhook logic to clear update transients.
- Refactored asset enqueuing for better maintainability.
- Sanitized debug panel to display non-sensitive user data.

### Changed
- Updated `enqueue_admin_scripts` to use `WP2_UPDATE_PLUGIN_DIR` constant.

### Removed
- Deprecated `enqueue_vite_assets` method.

---

## [Unreleased]
