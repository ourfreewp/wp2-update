# WP2 Update REST API Documentation

The WP2 Update plugin exposes a secure REST API for its single-page application frontend.

- **Namespace:** All endpoints are under `/wp2-update/v1/`.
- **Authentication:** All endpoints require a logged-in user with the `manage_options` capability. Requests must include a valid `X-WP-Nonce` header. The system uses the generic `wp_rest` nonce for all authenticated calls.

---

## App Management (`/apps`)

| Resource      | Method | Endpoint             | Description                                                                 |
|---------------|--------|----------------------|-----------------------------------------------------------------------------|
| App List      | GET    | `/apps`              | Retrieves summaries of all configured GitHub Apps.                          |
| App Creation  | POST   | `/apps`              | Creates a new placeholder app record before the GitHub manifest flow begins (internal use). |
| App Deletion  | DELETE | `/apps/<id>`         | Deletes the specified app record and clears related cached tokens.           |

---

## Package Actions (`/packages`)

| Resource      | Method     | Endpoint                                 | Description                                                                |
|---------------|------------|------------------------------------------|----------------------------------------------------------------------------|
| Package List  | GET        | `/packages`                              | Retrieves all local packages grouped by management status.                  |
| Full Sync     | GET/POST   | `/packages/sync`                         | Forces a full synchronization of all packages with GitHub release data.     |
| Assign App    | POST       | `/packages/assign`                       | Links an unmanaged package to a specific GitHub App ID.                     |
| Action Handler| POST       | `/packages/action`                       | Executes an action (update, rollback, or install) on a specified package/version. |
| Release Notes | GET        | `/packages/<repo_slug>/release-notes`    | Fetches the full list of release notes (Markdown body) for a repository.    |
| Release Channel| POST      | `/packages/<repo_slug>/release-channel`  | Updates the version channel (e.g., stable or beta) for a package.           |

---

## Configuration & Credentials (`/credentials`)

| Resource      | Method | Endpoint                       | Description                                                                |
|---------------|--------|-------------------------------|----------------------------------------------------------------------------|
| Manifest      | POST   | `/credentials/generate-manifest` | Initiates the GitHub App creation flow and returns the manifest setup URL.  |
| Exchange Code | POST   | `/credentials/exchange-code`     | Exchanges the temporary GitHub code for permanent credentials (used after GitHub callback). |
| Manual Setup  | POST   | `/credentials/manual-setup`      | Allows saving App ID, Installation ID, and Private Key manually.            |

---

## Health & Logging (`/logs`)

| Resource      | Method | Endpoint         | Description                                                                |
|---------------|--------|------------------|----------------------------------------------------------------------------|
| Health Status | GET    | `/health`        | Runs all system, connectivity, and data integrity checks.                   |
| Live Stream   | GET    | `/logs/stream`   | Server-Sent Events (SSE) endpoint providing real-time, incremental log messages. |

---

## Polling Service (`/polling`)

| Resource      | Method | Endpoint             | Description                                                                 |
|---------------|--------|----------------------|-----------------------------------------------------------------------------|
| Polling Status| GET    | `/polling/status`    | Retrieves the current status of the Polling Service, including last run time and next scheduled run. |
| Enable Polling| POST   | `/polling/enable`    | Enables the Polling Service and sets the polling interval.                  |
| Disable Polling| POST  | `/polling/disable`   | Disables the Polling Service.                                               |

---

### **Packages**

These endpoints are for discovering, managing, and interacting with your software packages (plugins and themes).

| Client-Side Action | Event Name | Method & Endpoint | Purpose |
| :--- | :--- | :--- | :--- |
| Click "**Sync All**" button | `syncAllPackages` | `POST /packages/sync` | Forces a re-scan of all installed packages, checks their status against GitHub, and refreshes the UI. |
| Click "**Refresh**" button | `refreshPackages` | `POST /packages/refresh` | Triggers a fresh scan of packages to update the local package list. |
| Click "**Update**" button | `updatePackage` | `POST /packages/{repo_slug}/update` | Updates a single package to its latest available release. |
| Confirm "**Rollback**" modal | `rollbackPackage` | `POST /packages/{repo_slug}/rollback` | Rolls a package back to a specific version. The version is sent in the request body. |
| Confirm "**Assign App**" modal | `assignAppToPackage` | `POST /packages/assign` | Assigns an unmanaged package to be managed by a specific GitHub App. |
| Click "**View Release Notes**" | `fetchReleaseNotes` | `GET /packages/{repo_slug}/release-notes` | Retrieves the release history for a package to be displayed in a modal. |
| Change "**Release Channel**" | `updateReleaseChannel` | `POST /packages/{repo_slug}/release-channel` | Sets the release channel (e.g., 'stable', 'beta') for a package. The channel is sent in the request body. |

---

### **GitHub Apps & Credentials**

These endpoints are for the setup, management, and authentication of your GitHub Apps.

| Client-Side Action | Event Name | Method & Endpoint | Purpose |
| :--- | :--- | :--- | :--- |
| Load "Apps" tab | `listApps` | `GET /apps` | Retrieves a list of all currently connected GitHub Apps. |
| Submit "**Create New App**" flow | `createApp` | `POST /apps` | Creates a new, empty record for a GitHub App before it is configured. |
| Redirect to GitHub for manifest setup | `initiateAppCreation` | `POST /apps/manifest` | Generates a GitHub App manifest and setup URL to automate the creation of a new app. |
| Handle callback from GitHub after manifest setup | `exchangeCode` | `POST /apps/exchange-code` | Exchanges the temporary code from GitHub for permanent app credentials. |
| Submit "**Connect Existing App**" form | `addExistingApp` | `POST /apps/add-existing` | Connects an existing GitHub App by manually providing its credentials. |
| Submit "**Settings**" for an app | `updateAppSettings` | `PUT /apps/{id}` | Updates the settings for a specific, already connected GitHub App. |
| Click "**Delete**" button for an app | `deleteApp` | `DELETE /apps/{id}` | Deletes a connected GitHub App and its credentials. |
| Periodically check app status | `fetchAppStatus` | `GET /apps/{id}/status` | Retrieves the connection status for a specific app to verify communication with the GitHub API. |

---

### **System Health & Status**

This endpoint provides diagnostic information about the plugin and its environment.

| Client-Side Action | Event Name | Method & Endpoint | Purpose |
| :--- | :--- | :--- | :--- |
| Click "**Refresh**" on "Health" tab | `refreshHealthStatus` | `GET /health` | Re-runs all diagnostic checks and returns an updated, comprehensive health status report. |

---

### **Webhooks**

This is the single entry point for all real-time communication from GitHub.

| Client-Side Action | Event Name | Method & Endpoint | Purpose |
| :--- | :--- | :--- | :--- |
| (Triggered by GitHub) | `receiveWebhook` | `POST /webhook` | Receives all incoming webhooks from GitHub, validates the request, and schedules an asynchronous task to process the event. |

---

### **Removed / Deprecated Endpoints**

The following endpoints were identified as redundant during our review and have been removed from the final architecture to simplify the API.

| Removed Route(s) | Reason for Removal |
| :--- | :--- |
| `GET /nonce`, `POST /nonce/verify`, `GET /nonce/refresh` | **Redundant**. Nonce verification is now handled globally and consistently by the `Permissions` utility class via the `X-WP-Nonce` header on all authenticated requests. |
| `GET /logs`, `GET /logs/stream` | **Replaced**. The project has adopted a more robust logging strategy using the `Logger` class, which integrates with Query Monitor for a superior debugging experience. |

