/**
 * Escapes HTML characters in a string to prevent XSS.
 * @param {string} str The string to escape.
 * @returns {string} The escaped string.
 */
export const escapeHTML = (str) => {
    if (typeof str !== 'string') return '';
    const p = document.createElement('p');
    p.appendChild(document.createTextNode(str));
    return p.innerHTML;
};

/**
 * Creates a debounced function that delays invoking the provided function until after the specified delay.
 * @param {Function} func - The function to debounce.
 * @param {number} delay - The delay in milliseconds.
 * @returns {Function} - The debounced function.
 */
export const debounce = (func, delay) => {
    let timeout;
    return (...args) => {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), delay);
    };
};

export { escapeHTML as escapeHtml };

/**
 * Standardized JavaScript Logger Utility
 */
const Logger = (() => {
    const isDebugMode = true; // Set this to false to disable debug logs

    const log = (level, ...args) => {
        if (isDebugMode) {
            const timestamp = new Date().toISOString();
            console[level](`[${timestamp}]`, ...args);
        }
    };

    return {
        debug: (...args) => log('log', '[DEBUG]', ...args),
        info: (...args) => log('info', '[INFO]', ...args),
        warn: (...args) => log('warn', '[WARN]', ...args),
        error: (...args) => log('error', '[ERROR]', ...args),
    };
})();

export { Logger };
