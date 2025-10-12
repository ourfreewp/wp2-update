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

const withRequiredManifestFields = (manifest) => {
	const ensured = { ...(manifest || {}) };
	const redirectUrl = window.wp2UpdateData?.redirectUrl;

	if (redirectUrl) {
		if (!ensured.redirect_url) {
			ensured.redirect_url = redirectUrl;
		}

		const callbacks = Array.isArray(ensured.callback_urls) ? [...ensured.callback_urls] : [];
		if (!callbacks.includes(redirectUrl)) {
			callbacks.push(redirectUrl);
		}
		ensured.callback_urls = callbacks;
	}

	return ensured;
};

const buildDefaultManifestDraft = () => {
	const siteName = window.wp2UpdateData?.siteName;
	const manifest = withRequiredManifestFields(defaultManifest);

	return {
		name: manifest?.name || (siteName ? `${siteName} Updater` : ''),
		accountType: 'user',
		organization: '',
		manifestJson: JSON.stringify(
			manifest && Object.keys(manifest).length ? manifest : {},
			null,
			2
		),
		encryptionKey: '', // Ensure this is tracked in the state
	};
};

// Unified state atom
export const unified_state = atom({
	status: STATUS.LOADING,
	isProcessing: false,
	message: '',
	details: {},
	manifestDraft: buildDefaultManifestDraft(),
	packages: [],
	allPackages: [],
	unlinkedPackages: [],
	error: null,
	polling: { active: false },
	apps: [], // List of apps
	selectedAppId: null, // Currently selected app ID
});

// Centralized state management
export const updateUnifiedState = (newState) => {
	const currentState = unified_state.get();
	const updates = typeof newState === 'function' ? newState(currentState) : newState;

	// Validate incoming updates
	if (updates && typeof updates !== 'object') {
		console.warn('updateUnifiedState: Invalid state update provided.', updates);
		return;
	}

	// Add debug log to inspect state updates
	console.debug('updateUnifiedState: Applying updates', updates);

	const { packages: packagesUpdate, selectedAppId, ...restUpdates } = updates || {};

	// Ensure packagesUpdate is an array of valid objects
	const allPackages = Array.isArray(packagesUpdate)
		? packagesUpdate.filter(pkg => pkg && typeof pkg === 'object')
		: currentState.allPackages || [];

	const packagesForState = allPackages;

	unified_state.set({
		...currentState,
		...(restUpdates || {}),
		allPackages,
		packages: packagesForState,
		selectedAppId: selectedAppId || currentState.selectedAppId,
	});
};

// Add methods to manage apps
export const addApp = (app) => {
	const currentApps = unified_state.get().apps;
	unified_state.set({
		...unified_state.get(),
		apps: [...currentApps, app],
	});
};

export const removeApp = (appId) => {
	const currentApps = unified_state.get().apps;
	unified_state.set({
		...unified_state.get(),
		apps: currentApps.filter(app => app.id !== appId),
	});
};

export const selectApp = (appId) => {
	updateUnifiedState({ selectedAppId: appId });
};
