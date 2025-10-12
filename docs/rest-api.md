# WP2 Update REST API Documentation

The WP2 Update plugin exposes a REST API to manage its functionality from the front-end interface. All endpoints are under the `/wp2-update/v1/` namespace.

**Authentication**: All endpoints require the user to be authenticated and have the `manage_options` capability. Requests must include a valid WordPress nonce, sent as the `X-WP-Nonce` header.

---

### Apps

#### List Apps

-   **Endpoint**: `GET /wp2-update/v1/apps`
-   **Description**: Retrieves a list of all configured GitHub Apps.
-   **Parameters**: None.
-   **Example Response**:
    ```json
    {
      "success": true,
      "data": {
        "apps": [
          {
            "id": "app-uuid-123",
            "name": "My Production App",
            "status": "installed",
            "account_type": "organization",
            "package_count": 5
          }
        ]
      }
    }
    ```

---

#### Create App

-   **Endpoint**: `POST /wp2-update/v1/apps`
-   **Description**: Creates a new GitHub App configuration.
-   **Parameters**:
    -   `name` (string, required): The display name for the app.
-   **Example Request**:
    ```json
    {
      "name": "New Staging App"
    }
    ```
-   **Example Response**:
    ```json
    {
      "success": true,
      "data": {
        "message": "App created successfully.",
        "app": {
          "id": "new-app-uuid-456",
          "name": "New Staging App",
          "status": "pending",
          "account_type": "user",
          "package_count": 0
        }
      }
    }
    ```

---

### Packages

#### Sync Packages

-   **Endpoint**: `GET /wp2-update/v1/packages/sync`
-   **Description**: Scans the WordPress installation for managed plugins and themes and synchronizes their status with the connected GitHub repositories.
-   **Parameters**: None.
-   **Example Response**:
    ```json
    {
      "success": true,
      "data": {
        "packages": [
          {
            "name": "My Awesome Plugin",
            "repo": "owner/my-awesome-plugin",
            "installed": "1.0.0",
            "latest": "1.1.0",
            "status": "outdated",
            "is_managed": true
          }
        ],
        "unlinked_packages": []
      }
    }
    ```

---

#### Manage a Package

-   **Endpoint**: `POST /wp2-update/v1/packages/manage`
-   **Description**: Performs an action on a package, such as updating, installing, or rolling back.
-   **Parameters**:
    -   `action` (string, required): The action to perform. Can be `install`, `update`, or `rollback`.
    -   `repo_slug` (string, required): The repository slug of the package (e.g., `owner/repo`).
    -   `version` (string, required): The version to install or roll back to.
-   **Example Request**:
    ```json
    {
      "action": "update",
      "repo_slug": "owner/my-awesome-plugin",
      "version": "1.1.0"
    }
    ```
-   **Example Response**:
    ```json
    {
      "success": true,
      "data": {
        "message": "Package managed successfully."
      }
    }
    ```

---

### Connection

#### Get Connection Status

-   **Endpoint**: `GET /wp2-update/v1/connection-status`
-   **Description**: Checks the connection status to GitHub for the configured app.
-   **Parameters**: None.
-   **Example Response**:
    ```json
    {
      "success": true,
      "data": {
        "status": "installed",
        "message": "Connection to GitHub succeeded."
      }
    }
    ```