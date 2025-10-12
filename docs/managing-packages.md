# Managing Your Packages

Once you've connected a GitHub App, the WP2 Update dashboard becomes your central hub for managing your GitHub-hosted plugins and themes.

## Viewing Package Status

The main "Packages" tab gives you a complete overview of all your managed packages. For each package, you can see:

-   **Package**: The name of the plugin or theme.
-   **Type**: Whether it's a "plugin" or a "theme."
-   **Installed**: The version currently installed on your site.
-   **Latest**: The latest version available on GitHub.
-   **Status**: The current status of the package. This can be:
    -   `Up to date`: The installed version is the latest version.
    -   `Update available`: A new version is available on GitHub.
    -   `Unmanaged`: The package has an "Update URI" but is not associated with any of your connected GitHub Apps.



## Updating a Package

When a new version of a package is available, an "Update" button will appear in the actions column.

1.  To update the package, simply click the **Update** button.
2.  A confirmation modal will appear. Click **Confirm Update** to proceed.
3.  The plugin will download the new version from GitHub and install it. A success message will be displayed upon completion.

## Rolling Back a Package

If an update causes an issue, you can easily roll back to a previous version.

1.  In the actions column for the package, click the **Rollback** button.
2.  A modal will open with a dropdown list of previously published releases.
3.  Select the version you want to roll back to and click **Confirm Rollback**.
4.  The plugin will download and install the selected version, overwriting the current one.

## Assigning a Package to an App

If a package is listed as "Unmanaged," you'll need to assign it to one of your connected GitHub Apps.

1.  Click the **Assign App** button in the actions column for the unmanaged package.
2.  In the modal that opens, select the appropriate GitHub App from the dropdown list.
3.  Click **Assign App**.

The package will now be managed by the selected app, and the plugin will be able to fetch updates for it.