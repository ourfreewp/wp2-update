// Initialization for Tabby.js

import Tabby from 'tabbyjs';

export const initializeTabs = () => {
    const tabs = new Tabby('[data-tabs]');
    return tabs;
};