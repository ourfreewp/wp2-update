import { atom, computed } from 'nanostores';

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
		})); // Removed syncError from the persisted payload
	} catch {}
};

const restore = () => {
	try {
		const raw = sessionStorage.getItem('wp2-update:state');
		if (!raw) return;
		const obj = JSON.parse(raw);
		if (obj && typeof obj === 'object' && Array.isArray(obj.packages)) {
			app_state.set({ ...app_state.get(), ...obj });
		}
	} catch {}
};

restore();
app_state.subscribe(persist);