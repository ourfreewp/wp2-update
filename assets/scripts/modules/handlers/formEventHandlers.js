import { updateState } from '../state/store.js';
import { debounce } from '../utils/debounce.js';

const handleFormInput = debounce((event) => {
    const { name, value, type, checked } = event.target;
    const formValue = type === 'checkbox' ? checked : value;
    updateState(state => ({
        ...state,
        form: {
            ...state.form,
            [name]: formValue,
        },
    }));
}, 200);

export function registerFormHandlers() {
    document.addEventListener('input', (event) => {
        // Only handle inputs inside forms that are part of this app
        if (event.target.closest('form.wp2-form')) {
            handleFormInput(event);
        }
    });
}
