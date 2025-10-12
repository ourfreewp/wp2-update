# Getting Started with WP2 Update

Welcome to WP2 Update! This guide will walk you through the process of installing the plugin and connecting your first GitHub App.

## Step 1: Install the Plugin

1.  Download the latest version of the WP2 Update plugin from the [releases page](https://github.com/ourfreewp/wp2-update/releases).
2.  In your WordPress admin dashboard, navigate to **Plugins > Add New**.
3.  Click the **Upload Plugin** button at the top of the page.
4.  Choose the ZIP file you downloaded and click **Install Now**.
5.  Once the installation is complete, click **Activate Plugin**.

You will now have a "WP2 Updates" menu item in your WordPress admin sidebar.

## Step 2: Add a New GitHub App

1.  Navigate to the **WP2 Updates** page in your admin dashboard.
2.  Click the **Add GitHub App** button. This will open the setup wizard.

    

3.  In the wizard, you will need to provide the following information:
    -   **App name**: A descriptive name for your app (e.g., "My Site's Updater").
    -   **Encryption key**: A secure, random string of at least 16 characters. **It is critical that you save this key in a secure location**, as you will need it if you ever need to reconnect the plugin.
    -   **Account type**: Choose whether the app will belong to your personal GitHub account or an organization.

    

4.  Click the **Generate manifest** button.

## Step 3: Create the App on GitHub

1.  After generating the manifest, the wizard will display a block of JSON code and a button to **Open GitHub**.
2.  Click the **Copy manifest** button to copy the JSON to your clipboard.
3.  Click the **Open GitHub** button. This will take you to the "Create a new GitHub App" page on GitHub, pre-filled for your user or organization.
4.  Paste the manifest code you copied into the text area on the GitHub page.

    

5.  Click the **Create GitHub App** button at the bottom of the page.

## Step 4: Install the App

1.  After creating the app, GitHub will redirect you to the app's settings page. From here, you need to install it.
2.  Click the **Install App** tab in the left sidebar.
3.  Click the **Install** button next to your user or organization name.
4.  You will be asked to choose which repositories the app can access. You can either grant it access to all repositories or select specific ones.

    

5.  Click the **Install** button.

## Step 5: Finalize the Connection

Once the app is installed, the WP2 Update plugin will automatically detect the connection. You can return to the WP2 Updates page in your WordPress admin, and you should now see your connected app and any managed packages.

Congratulations, you're all set! The plugin will now automatically check for new releases in your selected repositories.