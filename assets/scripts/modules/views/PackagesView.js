import { __ } from '@wordpress/i18n';
import { PackageRow } from '../components/PackageRow.js';
import { store } from '../state/store.js';

export class PackagesView {
    /**
     * Renders the packages table.
     * @param {HTMLElement} rootElement - The element to render into.
     */
    render(rootElement) {
        const { packages } = store.get();
        rootElement.innerHTML = `
            <header class="wp2-panel-header">
                <h2>${__("Packages", "wp2-update")}</h2>
            </header>
			<div class="wp2-table-wrapper">
				<table class="wp2-table">
					<thead>
						<tr>
							<th>${__("Package", "wp2-update")}</th>
							<th>${__("Status", "wp2-update")}</th>
							<th>${__("Installed Version", "wp2-update")}</th>
							<th>${__("Latest Release", "wp2-update")}</th>
							<th class="wp2-table-cell__actions">${__("Actions", "wp2-update")}</th>
						</tr>
					</thead>
					<tbody>
                        ${packages.length > 0 ? packages.map(PackageRow).join('') : `<tr><td colspan="5">${__("No packages found. Sync with GitHub to see your packages.", "wp2-update")}</td></tr>`}
					</tbody>
				</table>
			</div>
		`;
    }
}
