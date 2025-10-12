import { renderActionButton } from './renderActionButton.js';
import { RollbackModal } from './RollbackModal.js';

export const renderPackageActions = (pkg) => {
	const actions = [];

	if (pkg.has_update) {
		actions.push(renderActionButton('update', 'Update', '🔄'));
	}

	if (pkg.is_installed) {
		actions.push(renderActionButton('rollback', 'Rollback', '↩️', () => {
			const modal = RollbackModal(pkg, () => {
				document.body.removeChild(modal);
			});
			document.body.appendChild(modal);
		}));
	} else {
		actions.push(renderActionButton('install', 'Install', '⬇️'));
	}

	return actions.join('');
};