# Troubleshooting Common Issues

The **Health** tab provides the primary tools for diagnostics. Use the system checks and the **Live Log Stream** to identify issues related to connectivity, file operations, and security.

## Live Log Stream (Health Tab)

This stream displays real-time activity, providing crucial context for errors:

- **[SECURITY]**: Failed webhook signature checks, unauthorized REST API access, or invalid nonce use.
- **[ERROR]**: Indicates API connection failures, issues decrypting keys, or failed file system operations during an update/rollback.
- **[INFO]**: Shows successful synchronization, processed webhooks, and completed actions.

## Common Issues

| Issue                   | Cause & Solution                                                                                                                                                                                                 |
|-------------------------|------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| **Package Not Showing** | **Missing Header**: Ensure the `Update URI` header is correctly added to the plugin/theme main file. Refer to the 'Getting Started' guide for details.<br>After adding, click **Sync All**.                             |
| **Update Not Appearing**| **Caching/Release Format**: Click **Sync All** to force transient clearing.<br>Ensure the GitHub release is **Published** (not a draft) and has a version tag higher than the installed version.                |
| **Connection Failed**   | **App Permissions**: Verify your GitHub App has read-access to "Contents" and "Metadata" for all managed repositories.<br>Check the **Webhooks** tab on GitHub for recent delivery failures.                     |
| **Rollback Failed (File Error)** | **Server Permissions**: The WordPress server often lacks write permissions for file operations.<br>Ensure the WordPress install can create/modify files.<br>Check the `[ERROR]` logs for specific file operation failures. |
| **API Requests Fail**   | **Nonce Issue**: If multiple tabs are open, your nonce may expire.<br>Try navigating between tabs, or check the console for nonce errors.<br>(Note: Our internal API check handles generic REST nonces, but external tools may require action-specific ones). |

## New Troubleshooting Steps

### Polling Service Issues

#### Polling Not Running
- **Cause**: The Polling Service may be disabled in the settings.
- **Solution**: Navigate to the **Settings** tab and ensure the Polling Service is enabled. Adjust the polling interval if necessary.

#### API Rate Limit Errors
- **Cause**: Too many requests to the GitHub API within a short period.
- **Solution**: Check the **Logs** tab for rate limit warnings. Consider increasing the polling interval or upgrading your GitHub App's API rate limit by authenticating with a higher-tier token.

#### Polling Logs Not Updating
- **Cause**: The logging system may be disabled or encountering errors.
- **Solution**: Verify that logging is enabled in the **Settings** tab. Check the **wp2_update_logs** database table for recent entries.

## WP-CLI Debugging

Use the following commands for quick diagnostics:

```sh
wp wp2-update sync
```
_Forces a scan of all local packages and synchronizes their status with GitHub._

```sh
wp wp2-update update owner/repo
```
_Forces an update on a specific package._
