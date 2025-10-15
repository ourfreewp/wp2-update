export const StandardModal = ({ title, bodyContent, footerActions }) => `
    <div class="wp2-modal-header">
        <h2>${title}</h2>
    </div>
    <div class="wp2-modal-body">
        ${bodyContent}
    </div>
    <div class="wp2-modal-footer d-flex justify-content-between">
        ${footerActions.map(action => `<button type="button" class="wp2-btn ${action.class}" ${action.attributes}>${action.label}</button>`).join('')}
    </div>
`;
