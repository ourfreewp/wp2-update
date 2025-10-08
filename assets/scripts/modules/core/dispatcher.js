// Tiny action dispatcher + helpers
const registry = new Map();

/**
 * @typedef {(el?: HTMLElement) => Promise<void>|void} ActionHandler
 */

/**
 * @param {string} type
 * @param {ActionHandler} handler
 */
export const register_action = (type, handler) => {
	registry.set(type, handler);
};

/**
 * @param {string} type
 * @param {HTMLElement} [el]
 */
export const dispatch_action = async (type, el) => {
	const fn = registry.get(type);
	if (!fn) throw new Error(`Unknown action: ${type}`);
	return fn(el);
};

export const with_button_busy = async (button, fn) => {
	if (!button) return fn();
	const original = button.innerHTML;
	button.innerHTML = '<span class="spinner is-active"></span>';
	button.disabled = true;
	try { return await fn(); }
	finally {
		// Recover only if still in DOM
		if (document.body.contains(button)) {
			button.innerHTML = original;
			button.disabled = false;
		}
	}
};