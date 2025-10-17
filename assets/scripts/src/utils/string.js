/**
 * Escapes HTML characters in a string to prevent XSS.
 * @param {string} str The string to escape.
 * @returns {string} The escaped string.
 */
export const escapeHtml = (str) => {
    if (typeof str !== 'string') return '';
    const p = document.createElement('p');
    p.textContent = str;
    return p.innerHTML;
};
