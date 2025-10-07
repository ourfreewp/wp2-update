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
  "message": "Cache cleared and update check triggered."
}
```

## Using Client ID and Client Secret

GitHub Apps can use OAuth credentials to identify users. Below are the steps to use Client ID and Client Secret for authentication:

1. **Client ID**: This is a unique identifier for your GitHub App. Example: `Iv23likg3XVaGw76jDJ5`
2. **Client Secret**: This is used to authenticate as the application to the API. Ensure it is stored securely and not exposed publicly.

### Steps to Authenticate
- Use your Client ID to initiate the OAuth flow.
- Exchange the authorization code for an access token using the Client Secret.

### Example
```bash
curl -X POST https://github.com/login/oauth/access_token \
  -H "Accept: application/json" \
  -d "client_id=Iv23likg3XVaGw76jDJ5" \
  -d "client_secret=YOUR_CLIENT_SECRET" \
  -d "code=AUTHORIZATION_CODE"
```

### Notes
- Always keep your Client Secret confidential.
- Refer to the [GitHub Developer Documentation](https://docs.github.com/en/developers/apps) for more details.

---

For more details, refer to the plugin's README or contact support.