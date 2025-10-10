import { atom } from 'nanostores';

const parseDefaultManifest = () => {
	try {
		const manifest = window.wp2UpdateData?.manifest;
		return typeof manifest === 'object' && manifest ? manifest : {};
	} catch (error) {
		console.warn('WP2 Update: unable to parse default manifest.', error);
		return {};
	}
};

const defaultManifest = parseDefaultManifest();

const buildDefaultManifestDraft = () => {
	const siteName = window.wp2UpdateData?.siteName;

	return {
		name: defaultManifest?.name || (siteName ? `${siteName} Updater` : ''),
		accountType: 'user',
		organization: '',
		manifestJson: (() => {
			try {
				return JSON.stringify(
					defaultManifest && Object.keys(defaultManifest).length ? defaultManifest : {},
					null,
					2
				);
			} catch {
				return '{}';
			}
		})(),
	};
};

/**
 * @typedef {'loading'|'not_configured'|'not_configured_with_packages'|'configuring'|'app_created'|'connecting'|'installed'|'error'} ConnectionStatus
 */

/** @type {import('nanostores').WritableAtom<{
 * status: ConnectionStatus;
 * isProcessing: boolean;
 * message: string;
 * details: Record<string, any>;
 * manifestDraft: {
 *   name: string;
 *   accountType: 'user'|'organization';
 *   organization: string;
 *   manifestJson: string;
 * };
 * packages: Array<any>;
 * unlinkedPackages: Array<any>;
 * error: string|null;
 * polling: { active: boolean };
 * }>} */
export const dashboard_state = atom({
	status: 'loading',
	isProcessing: false,
	message: '',
	details: {},
	manifestDraft: buildDefaultManifestDraft(),
	packages: [],
	unlinkedPackages: [],
	error: null,
	polling: { active: false },
});
