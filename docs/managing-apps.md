# Managing GitHub Apps

This document provides a comprehensive guide to managing GitHub Apps within the WP2 Update plugin. It covers the app creation flows, interactions, and planned workflows.

---

## App Creation Flows

### 1. Manual Setup Flow
The manual setup flow allows users to connect an existing GitHub App by providing its credentials.

#### Steps:
1. Open the "Add GitHub App Wizard" modal.
2. Select the **Manual** setup option.
3. Enter the following details:
   - **App Name**: The name of the GitHub App.
   - **Client ID**: The Client ID of the GitHub App.
   - **Client Secret**: The Client Secret of the GitHub App.
4. Review the pre-filled read-only fields:
   - **Webhook URL**: The URL where GitHub will send webhook events.
   - **Callback URL**: The URL GitHub redirects to after app creation.
5. Submit the form to save the app credentials.

#### Interactions:
- The Webhook URL and Callback URL are dynamically generated and displayed as read-only fields.
- The app credentials are securely stored in the database.

---

### 2. Magic Setup Flow
The magic setup flow simplifies the process by generating a GitHub App manifest and redirecting the user to GitHub to create the app.

#### Steps:
1. Open the "Add GitHub App Wizard" modal.
2. Select the **Magic** setup option.
3. Enter the following details:
   - **App Name**: The name of the GitHub App.
   - **Webhook Secret**: A secret key used to validate webhook payloads.
   - **App Type**: Choose between "Personal App" or "Organization App".
   - **Organization Username** (if applicable): The slug of the organization.
4. Click **Next** to generate the app manifest.
5. The plugin calls the `generate_manifest` endpoint to create the manifest.
6. The user is redirected to GitHub to complete the app creation process.

#### Interactions:
- The manifest includes the Webhook URL, Callback URL, and other required fields.
- After app creation, GitHub redirects to the Callback URL, where the app details are processed and stored.

---

## Planned Workflows

### 1. App Credential Updates
- Users can update the credentials of an existing app (e.g., Client Secret).
- The plugin validates the new credentials before saving them.

### 2. Webhook Event Handling
- The `/webhook` endpoint processes incoming events from GitHub.
- Events are validated using the Webhook Secret.
- Supported events include:
  - `push`
  - `release`
  - `pull_request`

### 3. App Deletion
- Users can delete an app from the plugin.
- Deletion removes all associated credentials and webhook configurations.

### 4. App Assignment
- Apps can be assigned to specific plugins or themes.
- The assignment ensures that updates are linked to the correct app.

---

## REST API Endpoints

### 1. `wp2-update/v1/apps`
- **GET**: List all apps.
- **POST**: Create a new app.

### 2. `wp2-update/v1/credentials/generate-manifest`
- **POST**: Generate a GitHub App manifest.

### 3. `wp2-update/v1/webhook`
- **POST**: Handle incoming webhook events.

---

## Remaining Tasks

### Backend
1. **Fix Missing Methods in `PackageService`**:
   - Implement the following methods to support app-related operations:
     - `getManagedPlugins`
     - `getManagedThemes`
     - `processPackage`

2. **Fix Constructor Arguments**:
   - Ensure the `ReleaseService` constructor is called with the correct arguments in `AppService.php`.
   - Ensure the `PackageService` constructor is called with the correct arguments in `AppService.php`.

3. **Test `create_app` Endpoint**:
   - Verify that the `create_app` endpoint creates apps successfully and returns the expected response.

### Frontend
4. **Test End-to-End Create App Flow**:
   - Test the entire flow, from opening the modal to creating an app and updating the apps table.

---

## Open Questions

1. **Webhook Event Handling**:
   - Are there additional GitHub events we need to support beyond `push`, `release`, and `pull_request`?

2. **Error Handling**:
   - How should errors during the app creation process (e.g., invalid credentials) be communicated to the user?

3. **App Assignment**:
   - Should we allow multiple apps to be assigned to a single plugin or theme?

4. **Manifest Customization**:
   - Are there additional fields we should allow users to customize in the GitHub App manifest?

5. **Security**:
   - Are there any additional security measures we should implement for webhook validation or app credential storage?

---

## AppDTO Class

The `AppDTO` (Data Transfer Object) class is a new addition to the WP2 Update plugin. It simplifies the handling of app-related data by providing a structured format for transferring data between the backend and frontend.

### Key Features
- **Data Validation**: Ensures that all required fields are present and correctly formatted.
- **Serialization**: Converts app data into JSON for easy transmission via REST API endpoints.
- **Decoupling**: Reduces dependencies between the app management logic and the REST API layer.

### Usage
The `AppDTO` class is used in the following scenarios:
1. **App Creation**: Validates and serializes data before saving it to the database.
2. **App Listing**: Formats app data for display in the WP2 Update dashboard.
3. **App Updates**: Ensures that updated credentials are correctly validated and stored.

### Example
```php
$appDTO = new AppDTO([
    'name' => 'My GitHub App',
    'client_id' => 'abc123',
    'client_secret' => 'xyz789',
    'webhook_secret' => 'secret123',
]);

// Serialize to JSON
$json = $appDTO->toJson();

// Validate data
if ($appDTO->isValid()) {
    // Save to database
}
```

### Benefits
- Simplifies app management workflows.
- Improves code readability and maintainability.
- Reduces the likelihood of errors during data handling.

This document will be updated as new features and workflows are implemented.