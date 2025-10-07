# Build Instructions

To create a valid plugin ZIP file for distribution, follow these steps:

1. **Install Dependencies:**
   Ensure all required dependencies are installed.
   ```bash
   composer install --no-dev
   npm install
   ```

2. **Build Assets:**
   Compile the plugin's assets using the build script.
   ```bash
   npm run build
   ```

3. **Prepare for Distribution:**
   Ensure the `.distignore` file is configured correctly to exclude unnecessary files.

4. **Create the ZIP File:**
   Use the following command to create a ZIP file of the plugin:
   ```bash
   zip -r wp2-update.zip . -x@.distignore
   ```

5. **Validate the ZIP File:**
   Test the ZIP file by installing it on a fresh WordPress installation to ensure everything works as expected.