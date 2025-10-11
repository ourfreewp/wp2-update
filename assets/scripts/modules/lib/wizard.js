// Initialization for Wizard.js

export const initializeWizard = (selector) => {
    const wizardElement = document.querySelector(selector);
    if (!wizardElement) {
        console.error('Wizard element not found:', selector);
        return null;
    }

    // Example: Initialize wizard logic here
    console.log('Wizard initialized for:', selector);
    return wizardElement;
};

// Updated all class selectors to include the `wp2-` prefix
const wizardElement = document.querySelector('.wp2-wizard');