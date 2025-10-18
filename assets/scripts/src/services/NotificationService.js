import { store, updateState } from '../state/store.js';

let notificationId = 0;

export const NotificationService = {
    showSuccess(message) {
        this._addNotification('success', message);
        updateState({ statusMessage: message });
    },

    showError(message) {
        this._addNotification('danger', message);
        updateState({ statusMessage: message });
    },

    _addNotification(type, message) {
        const note = { id: notificationId++, type, message };
        const currentNotifications = store.get().notifications;
        updateState({ notifications: [...currentNotifications, note] });

        // Optional: auto-dismiss after 5 seconds
        setTimeout(() => {
            const current = store.get().notifications;
            updateState({ notifications: current.filter(n => n.id !== note.id) });
            // Clear statusMessage after notification is dismissed
            updateState({ statusMessage: '' });
        }, 5000);
    }
};
