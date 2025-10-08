import Tabby from 'tabbyjs';
import { computePosition, offset, flip, shift } from '@floating-ui/dom';

export const init_ui = () => {
	// Tabs
	if (document.querySelector('[data-tabs]')) new Tabby('[data-tabs]');

	// Tooltip
	const trigger = document.getElementById('webhook-tooltip-trigger');
	const tooltip = document.getElementById('webhook-tooltip');
	if (trigger && tooltip) {
		const position = () => computePosition(trigger, tooltip, {
			placement: 'top', middleware: [offset(8), flip(), shift({ padding: 5 })],
		}).then(({ x, y }) => Object.assign(tooltip.style, { left: `${x}px`, top: `${y}px` }));
		const show = () => { tooltip.style.display = 'block'; position(); };
		const hide = () => { tooltip.style.display = 'none'; };
		['mouseenter','focus'].forEach(e=>trigger.addEventListener(e, show));
		['mouseleave','blur'].forEach(e=>trigger.addEventListener(e, hide));
	}
};