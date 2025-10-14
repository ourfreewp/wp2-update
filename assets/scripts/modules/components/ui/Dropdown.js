/**
 * Renders a dropdown menu with keyboard navigation support.
 * @param {Array} items - The list of dropdown items.
 * @returns {string} The HTML for the dropdown menu.
 */
export const Dropdown = (items = []) => {
    if (!items.length) {
        return '';
    }

    const dropdownId = `dropdown-${Math.random().toString(36).substr(2, 9)}`;

    return `
        <div class="dropdown" id="${dropdownId}" tabindex="0">
            <button class="dropdown-toggle" aria-haspopup="true" aria-expanded="false">Actions</button>
            <ul class="dropdown-menu" role="menu" hidden>
                ${items.map((item, index) => `
                    <li>
                        <button id="${item.id}" class="dropdown-item" tabindex="-1" data-index="${index}" role="menuitem">
                            ${item.icon ? `<span class="icon">${item.icon}</span>` : ''}
                            ${item.label}
                        </button>
                    </li>
                `).join('')}
            </ul>
        </div>
    `;
};

// Add event listeners for keyboard navigation
document.addEventListener('click', (event) => {
    const toggle = event.target.closest('.dropdown-toggle');
    if (toggle) {
        const dropdown = toggle.closest('.dropdown');
        const menu = dropdown.querySelector('.dropdown-menu');
        const isExpanded = toggle.getAttribute('aria-expanded') === 'true';

        // Toggle dropdown visibility
        toggle.setAttribute('aria-expanded', !isExpanded);
        menu.hidden = isExpanded;

        if (!isExpanded) {
            // Focus the first menu item
            const firstItem = menu.querySelector('[data-index="0"]');
            firstItem && firstItem.focus();
        }
    }
});

document.addEventListener('keydown', (event) => {
    const activeElement = document.activeElement;
    const dropdown = activeElement.closest('.dropdown');

    if (dropdown) {
        const menu = dropdown.querySelector('.dropdown-menu');
        const items = Array.from(menu.querySelectorAll('[data-index]'));
        const currentIndex = items.indexOf(activeElement);

        switch (event.key) {
            case 'ArrowDown':
                event.preventDefault();
                const nextIndex = (currentIndex + 1) % items.length;
                items[nextIndex].focus();
                break;
            case 'ArrowUp':
                event.preventDefault();
                const prevIndex = (currentIndex - 1 + items.length) % items.length;
                items[prevIndex].focus();
                break;
            case 'Escape':
                event.preventDefault();
                menu.hidden = true;
                dropdown.querySelector('.dropdown-toggle').setAttribute('aria-expanded', 'false');
                dropdown.querySelector('.dropdown-toggle').focus();
                break;
        }
    }
});