// ========================================================================
// File: src-js/modules/ui.js
// Description: Handles all direct UI interactions and initializations.
// ========================================================================

import Toastify from 'toastify-js';
import Tabby from 'tabbyjs';
import { computePosition, offset, flip, shift } from '@floating-ui/dom';

/**
 * Displays a non-blocking toast notification.
 * @param {string} text - The message to display.
 * @param {'success' | 'error'} type - The style of the toast.
 */
export const showToast = (text, type = 'success') => {
    Toastify({
        text,
        duration: 4000,
        gravity: 'bottom',
        position: 'right',
        stopOnFocus: true,
        style: {
            background: type === 'success' ? '#28a745' : '#dc3545',
            borderRadius: '4px',
            boxShadow: '0 3px 6px -1px rgba(0, 0, 0, 0.12), 0 10px 36px -4px rgba(0, 0, 0, 0.3)',
        },
    }).showToast();
};

/**
 * Initializes tooltips on the page using Floating UI for positioning.
 */
export const initTooltips = () => {
    const trigger = document.getElementById('webhook-tooltip-trigger');
    const tooltip = document.getElementById('webhook-tooltip');
    if (!trigger || !tooltip) return;

    const updatePosition = () => {
        computePosition(trigger, tooltip, {
            placement: 'top',
            middleware: [offset(8), flip(), shift({ padding: 5 })],
        }).then(({ x, y }) => {
            Object.assign(tooltip.style, { left: `${x}px`, top: `${y}px` });
        });
    };

    const show = () => {
        tooltip.style.display = 'block';
        updatePosition();
    };
    const hide = () => {
        tooltip.style.display = 'none';
    };

    ['mouseenter', 'focus'].forEach(event => trigger.addEventListener(event, show));
    ['mouseleave', 'blur'].forEach(event => trigger.addEventListener(event, hide));
};

/**
 * Initializes TabbyJS tabs if the required DOM elements are present.
 */
export const initTabs = () => {
    if (document.querySelector('[data-tabs]')) {
        new Tabby('[data-tabs]');
    }
};
