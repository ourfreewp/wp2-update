/**
 * Handles the submission of the manual credentials form.
 * @param {Event} event - The form submission event.
 */
export const onSubmitManualForm = (event) => {
    event.preventDefault();
    const formData = new FormData(event.target);
    console.log('Manual credentials submitted:', Object.fromEntries(formData));
    // Add logic to handle form submission.
};

/**
 * Renders the ManualCredentialsView component.
 * Displays a form for entering credentials manually.
 * @returns {string} - HTML string for the ManualCredentialsView.
 */
export const renderManualCredentialsForm = () => {
    return `
        <form id="wp2-manual-credentials-form">
            <label for="wp2-app-id">App ID:</label>
            <input type="text" id="wp2-app-id" name="app_id" required />

            <label for="wp2-private-key">Private Key:</label>
            <textarea id="wp2-private-key" name="private_key" required></textarea>

            <button type="submit">Submit</button>
        </form>
    `;
};