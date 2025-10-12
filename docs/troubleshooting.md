# Troubleshooting Common Issues

This guide provides solutions to some of the most common issues you might encounter while using the WP2 Update plugin.

### Connection to GitHub is failing.

If you're having trouble connecting to GitHub, here are a few things to check:

-   **Incorrect Encryption Key**: If you've had to manually re-enter your credentials, make sure you're using the exact same encryption key you created when you first set up the app.
-   **GitHub App Permissions**: Ensure that your GitHub App has the necessary permissions. It needs read-access to "Contents" and "Metadata" for the repositories it manages.
-   **Webhook Issues**: Go to your GitHub App's settings on GitHub and check the "Webhooks" tab. Make sure the webhook URL is correct and that there are no recent delivery errors.

### A plugin or theme isn't showing up in the dashboard.

For a plugin or theme to be managed by WP2 Update, it must have a specific header in its main file.

-   **For plugins**: The main plugin file (e.g., `my-plugin.php`) must have an `Update URI` header that points to the GitHub repository slug.
    ```php
    /*
     * Plugin Name: My Awesome Plugin
     * ...
     * Update URI: owner/my-awesome-plugin
     */
    ```
-   **For themes**: The `style.css` file must have an `Update URI` header.
    ```css
    /*
     * Theme Name: My Awesome Theme
     * ...
     * Update URI: owner/my-awesome-theme
     */
    ```

After adding the header, go to the WP2 Updates dashboard and click the **Sync All** button to force a refresh.

### An update is available on GitHub, but it's not showing in WordPress.

This can happen for a few reasons:

-   **Caching**: WordPress caches update information in transients. While the plugin's webhooks should clear this cache automatically, you can force it by clicking the **Sync All** button.
-   **Release Format**: Make sure your new release on GitHub is published (not a draft) and that it includes a ZIP file of the installable plugin or theme as a release asset.
-   **Version Numbers**: Ensure that the version number in your new release is higher than the currently installed version, following standard versioning practices (e.g., `1.1.0` is higher than `1.0.0`).

### Understanding the Debug Log

The "Debug" tab in the WP2 Update dashboard provides a log of all actions taken by the plugin. This can be very helpful for troubleshooting. Here are some common log entries and what they mean:

-   `[INFO] Webhook event received: release`: The plugin has successfully received a webhook from GitHub.
-   `[SECURITY] Webhook validation failed: Invalid signature`: The webhook signature was incorrect. This could indicate a misconfiguration of the webhook secret.
-   `[ERROR] Failed to fetch release data`: The plugin was unable to connect to the GitHub API to get release information. This could be due to a network issue or an invalid API token.

If you're still having trouble, the debug log is the best place to start. It will often provide a specific error message that can help you diagnose the problem.