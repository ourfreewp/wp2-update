# REST API Usage

WP2 Update provides a set of REST API endpoints to manage various plugin functionalities. Below is a detailed guide on how to use these endpoints.

## Base URL
All endpoints are prefixed with:
```
/wp-json/wp2-update/v1
```

## Endpoints

### 1. **Connection Status**
- **Endpoint:** `/connection-status`
- **Method:** `GET`
- **Description:** Retrieves the current connection status of the GitHub App.
- **Permission:** Requires `manage_options` capability.
- **Response Example:**
```json
{
  "status": "connected",
  "app_slug": "example-app"
}
```

### 2. **Test Connection**
- **Endpoint:** `/test-connection`
- **Method:** `POST`
- **Description:** Tests the connection for a specific GitHub App.
- **Permission:** Requires `manage_options` capability.
- **Request Body:**
```json
{
  "app_slug": "example-app"
}
```
- **Response Example:**
```json
{
  "success": true,
  "message": "Connection test successful!"
}
```

### 3. **Clear Cache and Force Check**
- **Endpoint:** `/clear-cache-force-check`
- **Method:** `POST`
- **Description:** Clears the cache and forces WordPress to recheck for updates.
- **Permission:** Requires `manage_options` capability.
- **Response Example:**
```json
{
  "success": true,
  "message": "Cache cleared and checks forced."
}
```

### 4. **GitHub Webhooks**
- **Endpoint:** `/github/webhooks`
- **Method:** `POST`
- **Description:** Handles incoming GitHub webhook events.
- **Permission:** Publicly accessible.
- **Request Example:**
```json
{
  "event": "push",
  "repository": "example-repo"
}
```
- **Response Example:**
```json
{
  "success": true,
  "message": "Webhook processed successfully."
}
```

---

For more details, refer to the plugin's README or contact support.