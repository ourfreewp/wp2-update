import { render_view, render_connecting_view } from '../components/views.js';
import { render_package_table } from '../components/table.js';
import { render_configure_manifest } from '../components/manifest.js';

export const manageViewTransition = (state) => {
    render_view(state.currentStage);

    switch (state.currentStage) {
        case 'configure-manifest':
            render_configure_manifest(state.manifestDraft);
            break;
        case 'connecting-to-github':
            render_connecting_view();
            break;
        case 'managing':
            render_package_table(state.packages, state.isLoading, state.syncError);
            break;
    }
};