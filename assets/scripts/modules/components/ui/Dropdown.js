/**
 * Renders a dropdown menu leveraging Bootstrap's native functionality.
 * @param {Array} items - The list of dropdown items.
 * @returns {string} The HTML for the dropdown menu.
 */
export const Dropdown = (items = []) => {
    if (!items.length) {
        return '';
    }

    return `
        <div class="dropdown">
            <button class="dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">Actions</button>
            <ul class="dropdown-menu">
                ${items.map(item => `
                    <li>
                        <button id="${item.id}" class="dropdown-item" data-action="${item.id}" role="menuitem">
                            ${item.icon ? `<span class="icon">${item.icon}</span>` : ''}
                            ${item.label}
                        </button>
                    </li>
                `).join('')}
            </ul>
        </div>
    `;
};