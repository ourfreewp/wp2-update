import { AppRow } from '../components/AppRow.js';
import { store } from '../state/store.js';
import { __ } from '@wordpress/i18n';

export class AppsView {
    /**
     * Renders the apps table into the given root element.
     * @param {HTMLElement} rootElement - The element to render the view into.
     */
    render(rootElement) {
        const { apps, packages } = store.get();
        rootElement.innerHTML = `
            <header class="wp2-panel-header">
                <h2>${__("GitHub Apps", "wp2-update")}</h2>
            </header>
			<div class="wp2-table-wrapper">
				<table class="wp2-table">
					<thead>
						<tr>
							<th>${__("App Name", "wp2-update")}</th>
							<th>${__("Account Type", "wp2-update")}</th>
							<th>${__("Packages", "wp2-update")}</th>
							<th class="wp2-table-cell__actions">${__("Actions", "wp2-update")}</th>
						</tr>
					</thead>
					<tbody>
						${apps.length > 0 ? apps.map(app => AppRow(app, packages)).join('') : `<tr><td colspan="4">${__("No GitHub Apps are configured.", "wp2-update")}</td></tr>`}
					</tbody>
				</table>
			</div>
		`;
    }
}
