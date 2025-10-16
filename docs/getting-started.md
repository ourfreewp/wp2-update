# Getting Started with WP2 Update

Welcome to WP2 Update! This guide walks you through connecting your first GitHub App to enable secure updates.

## Step 1: Install and Activate

1. **Download the plugin ZIP file.**
2. In WordPress, go to **Plugins > Add New**, click **Upload Plugin**, and activate the plugin.
3. Find the new **WP2 Updates** menu item in your admin sidebar.

## Step 2: Initialize the Setup Wizard

1. Click the **Add GitHub App** button (or **Get Started** if prompted).
2. In the wizard, provide:
    - **App Name**: A descriptive name (e.g., `Production Site Updater`).
    - **Encryption Key**: A secure, random string of at least 16 characters.  
      *Save this key securely; it's needed for decryption.*
3. Click the **Generate Manifest** button.

## Step 3: Create the App on GitHub

1. The wizard will display a manifest. Click **Copy manifest** and then **Open GitHub**.
2. You will be redirected to the GitHub App creation page. Paste the manifest JSON code into the text area.
3. Click **Create GitHub App**.

## Step 4: Install the App on Repositories

1. After creation, GitHub redirects you to the app's settings. Click the **Install App** tab.
2. Click the **Install** button next to your user or organization name.
3. Select which repositories the app can access.  
    *Only repositories granted access can be managed by WP2 Update.*
4. Click the **Install** button to confirm.

## Step 5: Finalize the Connection

- Once the app is installed, GitHub will attempt to redirect back to your WordPress site.
- The WP2 Update plugin automatically detects the successful connection, verifies the credentials, and begins fetching your packages.
- You should now see your connected app and packages ready for management in the dashboard.

## Step 6: Enable Polling Service (Optional)

The Polling Service ensures your packages are always up-to-date by periodically checking for new releases on GitHub.

1. Navigate to the **Settings** tab in the WP2 Update dashboard.
2. Locate the **Polling Service** section.
3. Toggle the switch to enable the service.
4. Adjust the polling interval as needed.

Enabling this service automates the update-checking process, ensuring you always have the latest versions of your packages.

## Important: Update URI Header

For WP2 Update to detect and manage updates for your plugin or theme, ensure the main file includes the following header:

```
Update URI: owner/repository-name
```

Replace `owner/repository-name` with the GitHub repository slug. After adding this header, click **Sync All** in the WP2 Update dashboard to refresh the package list.
