# Managing Your Packages

The WP2 Update dashboard gives you a complete overview of all your GitHub-hosted plugins and themes, allowing for seamless version control.

## Viewing Package Status

The main **Packages** tab provides status for all items containing a valid Update URI header:

- **Up to date**: The installed version matches the latest release on GitHub.
- **Update available**: A new version is available. Click **Update** to install it.
- **Unmanaged**: The package is installed locally but is not assigned to any of your connected GitHub Apps.

## Updating and Rollback

All upgrade and rollback actions use the secure WordPress Upgrader system, ensuring file permissions and transports are handled correctly.

| Action               | How to Execute                                                                                                                                      |
|----------------------|-----------------------------------------------------------------------------------------------------------------------------------------------------|
| **Update a Package** | Click the **Update** button. The plugin downloads the latest ZIP (using secure token authorization) and installs it.                                |
| **Rollback a Package** | Use the **Actions** dropdown menu and select **Rollback**. In the modal, select the desired previous version from the list and click **Confirm Rollback**. |

## Assigning an Unmanaged Package

Packages marked as **Unmanaged** cannot receive updates until they are linked to a GitHub App that has permission to access their repository.

1. Click the **Assign App** button for the unmanaged package.
2. Select the connected GitHub App from the dropdown list that has access to the package's repository.
3. Click **Assign App**.

The package is now managed and ready for synchronization.

## Creating a New Package

Use the **Create Package** button to launch the wizard:

1. **Select Type**: Choose whether to create a new Plugin or Theme based on a starter template.
2. **Configure**: Provide the package name and target repository name.
3. **Confirm**: The plugin creates the new repository on GitHub and registers it immediately within your WordPress installation.

## Polling Service

The Polling Service ensures that your packages are always up-to-date by periodically checking for new releases on GitHub. This service runs in the background and updates the package status automatically.

### Key Features
- **Automatic Updates**: Polls GitHub repositories at regular intervals to fetch the latest release information.
- **Configurable Intervals**: You can adjust the polling frequency in the plugin settings.
- **Error Handling**: Logs any issues encountered during polling, such as API rate limits or network errors.

### Enabling the Polling Service
1. Navigate to the **Settings** tab in the WP2 Update dashboard.
2. Locate the **Polling Service** section.
3. Toggle the switch to enable or disable the service.
4. Adjust the polling interval as needed.

### Viewing Polling Logs
All polling activities are logged for transparency. To view the logs:
1. Go to the **Logs** tab in the WP2 Update dashboard.
2. Filter by **Polling Service** to see relevant entries.
