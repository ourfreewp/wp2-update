/**
 * @file src-js/modules/ui.js
 * @description Handles UI interactions like toasts, tooltips, and tabs.
 */

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
            background: type === 'success' ? 'var(--wp2-color-success)' : 'var(--wp2-color-error)',
            borderRadius: 'var(--wp2-border-radius)',
            boxShadow: '0 3px 6px -1px rgba(0, 0, 0, 0.12), 0 10px 36px -4px rgba(0, 0, 0, 0.3)',
        },
    }).showToast();
};

/**
 * Initializes tooltips on the page using Floating UI for positioning.
 */
const initTooltips = () => {
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

    ['mouseenter', 'focus'].forEach((event) => trigger.addEventListener(event, show));
    ['mouseleave', 'blur'].forEach((event) => trigger.addEventListener(event, hide));
};

/**
 * Initializes TabbyJS tabs if the required DOM elements are present.
 */
const initTabs = () => {
    if (document.querySelector('[data-tabs]')) {
        new Tabby('[data-tabs]');
    }
};

/**
 * Initializes all base UI components.
 */
export const initUI = () => {
    initTooltips();
    initTabs();
};

/**
 * Creates and displays a non-blocking confirmation modal.
 * @param {string} message - The confirmation message to display.
 * @param {Function} onConfirm - Callback to execute if the user confirms.
 * @param {Function} [onCancel] - Optional callback to execute if the user cancels.
 */
export const showConfirmationModal = (message, onConfirm, onCancel) => {
    // Create the modal elements
    const modal = document.createElement('div');
    modal.className = 'wp2-modal';
    modal.setAttribute('role', 'dialog');
    modal.setAttribute('aria-labelledby', 'modal-title');
    modal.setAttribute('aria-describedby', 'modal-description');
    modal.setAttribute('aria-hidden', 'false');

    const overlay = document.createElement('div');
    overlay.className = 'wp2-modal-overlay';

    const content = document.createElement('div');
    content.className = 'wp2-modal-content';

    const messageEl = document.createElement('p');
    messageEl.id = 'modal-description';
    messageEl.textContent = message;

    const buttonContainer = document.createElement('div');
    buttonContainer.className = 'wp2-modal-buttons';

    const confirmButton = document.createElement('button');
    confirmButton.className = 'button button-primary';
    confirmButton.textContent = 'Confirm';
    confirmButton.setAttribute('aria-label', 'Confirm action');
    confirmButton.addEventListener('click', () => {
        document.body.removeChild(modal);
        onConfirm();
    });

    const cancelButton = document.createElement('button');
    cancelButton.className = 'button';
    cancelButton.textContent = 'Cancel';
    cancelButton.setAttribute('aria-label', 'Cancel action');
    cancelButton.addEventListener('click', () => {
        document.body.removeChild(modal);
        if (onCancel) onCancel();
    });

    // Append elements
    buttonContainer.appendChild(confirmButton);
    buttonContainer.appendChild(cancelButton);
    content.appendChild(messageEl);
    content.appendChild(buttonContainer);
    modal.appendChild(overlay);
    modal.appendChild(content);

    // Add the modal to the DOM
    document.body.appendChild(modal);
};