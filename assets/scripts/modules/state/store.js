import { atom } from 'nanostores';

export const STATUS = {
	LOADING: 'loading',
	NOT_CONFIGURED: 'not_configured',
	NOT_CONFIGURED_WITH_PACKAGES: 'not_configured_with_packages',
	CONFIGURING: 'configuring',
	MANUAL_CONFIGURING: 'manual_configuring',
	APP_CREATED: 'app_created',
	CONNECTING: 'connecting',
	INSTALLED: 'installed',
	ERROR: 'error',
};

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
		manifestJson: JSON.stringify(
			defaultManifest && Object.keys(defaultManifest).length ? defaultManifest : {},
			null,
			2
		),
		encryptionKey: '', // Ensure this is tracked in the state
	};
};

export const dashboard_state = atom({
	status: STATUS.LOADING,
	isProcessing: false,
	message: '',
	details: {},
	manifestDraft: buildDefaultManifestDraft(),
	packages: [],
	unlinkedPackages: [],
	error: null,
	polling: { active: false },
});

export const updateDashboardState = (updates) => {
	const currentState = dashboard_state.get();
	dashboard_state.set({
		...currentState,
		...updates,
	});
};
