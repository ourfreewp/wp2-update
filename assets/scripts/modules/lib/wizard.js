import { Logger } from '../utils.js';

// Initialization for Wizard.js

export const initializeWizard = (selector) => {
    const wizardElement = document.querySelector(selector);
    if (!wizardElement) {
        Logger.error('Wizard element not found:', selector);
        return null;
    }

    Logger.info('Wizard initialized for:', selector);
    return wizardElement;
};

const wizardElement = document.querySelector('.wp2-wizard');
