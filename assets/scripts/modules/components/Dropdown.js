/**
 * Renders a dropdown menu.
 * @param {Array} items - The list of dropdown items.
 * @returns {string} The HTML for the dropdown menu.
 */
export const Dropdown = (items = []) => {
    if (!items.length) {
        return '';
    }

    return `
        <div class="dropdown">
            <button class="dropdown-toggle">Actions</button>
            <ul class="dropdown-menu">
                ${items.map(item => `
                    <li>
                        <button id="${item.id}" class="dropdown-item">
                            ${item.icon ? `<span class="icon">${item.icon}</span>` : ''}
                            ${item.label}
                        </button>
                    </li>
                `).join('')}
            </ul>
        </div>
    `;
};