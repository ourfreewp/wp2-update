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
    const modal = document.getElementById('disconnect-modal');
    if (!modal) {
        console.error('Modal element not found in the DOM.');
        return;
    }

    const messageEl = modal.querySelector('.modal-message');
    const confirmButton = modal.querySelector('.modal-confirm');
    const cancelButton = modal.querySelector('.modal-cancel');

    if (messageEl) messageEl.textContent = message;

    const closeModal = () => {
        modal.classList.remove('is-visible');
        confirmButton.removeEventListener('click', handleConfirm);
        cancelButton.removeEventListener('click', handleCancel);
    };

    const handleConfirm = () => {
        closeModal();
        if (onConfirm) onConfirm();
    };

    const handleCancel = () => {
        closeModal();
        if (onCancel) onCancel();
    };

    confirmButton.addEventListener('click', handleConfirm);
    cancelButton.addEventListener('click', handleCancel);

    modal.classList.add('is-visible');
};

/**
 * Renders the package table with sync status and error handling.
 * @param {Array} packages - The list of packages to display.
 */
export const renderPackageTable = (packages) => {
    const tableBody = document.getElementById('package-table-body');
    tableBody.innerHTML = ''; // Clear existing content

    packages.forEach((pkg) => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${pkg.name}</td>
            <td>${pkg.version}</td>
            <td>${pkg.status}</td>
        `;
        tableBody.appendChild(row);
    });

    // Check for sync errors
    if (appState.get().syncError) {
        const errorRow = document.createElement('tr');
        errorRow.innerHTML = `
            <td colspan="5" style="color: red; text-align: center;">
                ${appState.get().syncError}
                <button id="retry-sync" style="margin-left: 10px;">Retry</button>
            </td>
        `;
        tableBody.appendChild(errorRow);

        document.getElementById('retry-sync').addEventListener('click', () => {
            appState.setKey('syncError', null);
            actions['sync-packages']();
        });

        return;
    }
};