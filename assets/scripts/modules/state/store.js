import { atom, computed } from 'nanostores';

const parse_default_manifest = () => {
	try {
		if (!window.wp2UpdateData || !window.wp2UpdateData.githubAppManifest) {
			return {};
		}
		const raw = JSON.parse(window.wp2UpdateData.githubAppManifest);
		return typeof raw === 'object' && raw ? raw : {};
	} catch (error) {
		console.warn('WP2 Update: unable to parse default manifest.', error);
		return {};
	}
};

const default_manifest = parse_default_manifest();
const pretty_default_manifest = (() => {
	try {
		return JSON.stringify(default_manifest, null, 2);
	} catch {
		return '{}';
	}
})();

const default_manifest_draft = {
	name: default_manifest?.name || (window.wp2UpdateData?.siteName ? `${window.wp2UpdateData.siteName} Updater` : ''),
	accountType: 'user',
	organization: '',
	manifestJson: pretty_default_manifest,
};

/**
 * @typedef {'pre-connection'|'credentials'|'syncing'|'managing'|'configure-manifest'|'post-configuration'} AppStage
 * @typedef {'idle'|'updating'|'rollback'|'error'} PackageStatus
 */

/** @type {import('nanostores').WritableAtom<{
 * currentStage: AppStage;
 * isLoading: boolean;
 * connection: {
 *  health: { lastSync: string; apiRemaining: string; webhookOk: boolean; };
 *  validation: { steps: Array<{text:string,status:'pending'|'success'|'error',detail:string}> };
 * };
 * packages: Array<{name:string,repo:string,installed?:string,releases?:any[],selectedVersion?:string,status:PackageStatus,errorMessage?:string}>;
 * syncError: string|null;
 * }>} */
export const app_state = atom({
	currentStage: 'pre-connection',
	isLoading: false,
	connection: {
		health: { lastSync: 'N/A', apiRemaining: 'N/A', webhookOk: false },
		validation: { steps: [] },
	},
	packages: [],
	syncError: null,
	manifestDraft: default_manifest_draft,
});

export const is_any_pkg_updating = computed(app_state, (s) =>
	s.packages.some((p) => p.status === 'updating')
);

export const is_action_disabled = computed(app_state, (s) =>
	s.isLoading || s.packages.some((p) => p.status === 'updating')
);

// Persist / restore
const persist = (s) => {
	try {
		sessionStorage.setItem('wp2-update:state', JSON.stringify({
			currentStage: s.currentStage,
			connection: s.connection,
			packages: s.packages,
			manifestDraft: s.manifestDraft,
		})); // Removed syncError from the persisted payload
	} catch {}
};

const restore = () => {
	try {
		const raw = sessionStorage.getItem('wp2-update:state');
		console.log('Restored raw state:', raw); // Debugging
		if (!raw) return;
		const obj = JSON.parse(raw);
		if (obj && typeof obj === 'object' && Array.isArray(obj.packages)) {
			app_state.set({ ...app_state.get(), ...obj, manifestDraft: obj.manifestDraft || default_manifest_draft });
		} else {
			console.warn('Restored state is invalid. Using default state.');
		}
	} catch (error) {
		console.error('Error restoring state:', error);
	}
};

restore();
app_state.subscribe(persist);
