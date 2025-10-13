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

