# Contributing to WP2 Update

We're thrilled that you're interested in contributing to WP2 Update! Your help is invaluable in making this plugin the best it can be.

## How to Contribute

There are many ways you can contribute to the project:

-   **Reporting Bugs**: If you find a bug, please open an issue on our [GitHub repository](https://github.com/ourfreewp/wp2-update/issues). Be sure to include a clear description of the issue, steps to reproduce it, and any relevant error messages.
-   **Suggesting Enhancements**: If you have an idea for a new feature or an improvement to an existing one, we'd love to hear it. Open an issue and describe your idea in as much detail as possible.
-   **Submitting Pull Requests**: If you're a developer and want to contribute code, we welcome pull requests.

## Development Setup

To get started with development, you'll need to have Node.js and Composer installed on your machine.

1.  Clone the repository:
    ```sh
    git clone [https://github.com/ourfreewp/wp2-update.git](https://github.com/ourfreewp/wp2-update.git)
    ```
2.  Install PHP dependencies:
    ```sh
    composer install
    ```
3.  Install JavaScript dependencies:
    ```sh
    npm install
    ```
4.  Start the development server:
    ```sh
    npm run dev
    ```

This will start the Vite development server with hot-reloading, so any changes you make to the front-end assets will be reflected in your browser immediately.

## Pull Request Process

1.  Fork the repository and create a new branch from `main`.
2.  Make your changes, ensuring that you follow the project's coding standards (see below).
3.  Before submitting your pull request, make sure that the linter passes:
    ```sh
    composer run lint
    npm run lint
    ```
4.  Submit your pull request with a clear description of the changes you've made.

## Coding Standards

-   **PHP**: We follow the WordPress Coding Standards.
-   **JavaScript**: We use ESLint with the recommended WordPress configuration.

We look forward to your contributions!