import { store } from '../state/store.js';

/**
 * Removes a notification from the store by index.
 * @param {number} index - The index of the notification to remove.
 */
export function removeNotification(index) {
  const { notifications } = store.get();
  const updatedNotifications = notifications.filter((_, i) => i !== index);
  store.set({ notifications: updatedNotifications });
}