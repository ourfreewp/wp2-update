# Troubleshooting WP2 Update

If you encounter issues while using WP2 Update, this guide will help you diagnose and resolve common problems.

## Common Issues

### 1. **GitHub App Connection Fails**
- **Symptoms:**
  - "Connection test failed" message.
  - Unable to fetch updates.
- **Solutions:**
  1. Verify that the GitHub App has the correct permissions:
     - Repository metadata: Read-only
     - Contents: Read-only
  2. Ensure the App ID and private key are correctly configured in the plugin settings.
  3. Check your server's internet connectivity.

### 2. **Updates Not Showing**
- **Symptoms:**
  - Updates for themes/plugins are not visible in the WordPress admin.
- **Solutions:**
  1. Clear the cache using the "Clear Cache and Force Check" button.
  2. Ensure the GitHub repository has a valid `release` or `tag`.
  3. Check the plugin logs for errors.

### 3. **Webhook Not Triggering Updates**
- **Symptoms:**
  - GitHub webhook events are not processed.
- **Solutions:**
  1. Verify the webhook URL in the GitHub repository settings.
  2. Ensure the webhook secret matches the one configured in the plugin.
  3. Check the plugin logs for webhook-related errors.

## Debugging Tools

### 1. **View Logs**
- Navigate to the "Event Log" tab in the plugin settings to view recent logs.
- Use the filter to narrow down logs by context (e.g., `github-app`, `update-check`).

### 2. **Enable Debug Mode**
- Add the following line to your `wp-config.php` file:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```
- Check the debug log at `wp-content/debug.log`.

## Contact Support
If you're unable to resolve the issue, please open a support ticket or create a GitHub issue with the following details:
- WordPress version
- PHP version
- Steps to reproduce the issue
- Relevant logs or error messages