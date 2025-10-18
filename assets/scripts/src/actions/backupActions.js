import { updateState } from '../state/store.js';
import { ConnectionService } from '../services/ConnectionService.js';
import { NotificationService } from '../services/NotificationService.js';

const connection = new ConnectionService();

export async function fetchBackups(query = '') {
  try {
    const backups = await connection.listBackups(query);
    updateState({ backups });
    return backups;
  } catch (e) {
    NotificationService.showError('Failed to load backups.');
    throw e;
  }
}

export async function restoreBackup(file, type) {
  try {
    const ok = await connection.restoreBackup(file, type);
    if (ok) {
      NotificationService.showSuccess('Backup restored successfully.');
    } else {
      NotificationService.showError('Restore failed.');
    }
    return ok;
  } catch (e) {
    NotificationService.showError(e.message || 'Restore failed.');
    throw e;
  }
}

export async function deleteBackup(file) {
  try {
    const ok = await connection.deleteBackup(file);
    if (ok) {
      NotificationService.showSuccess('Backup deleted.');
    } else {
      NotificationService.showError('Delete failed.');
    }
    return ok;
  } catch (e) {
    NotificationService.showError(e.message || 'Delete failed.');
    throw e;
  }
}

export async function deleteBackups(files = []) {
  try {
    const results = await connection.deleteBackups(files);
    const failed = results.filter(r => !r.deleted);
    if (failed.length) {
      NotificationService.showError(`${failed.length} file(s) failed to delete.`);
    } else {
      NotificationService.showSuccess('Selected backups deleted.');
    }
    return results;
  } catch (e) {
    NotificationService.showError(e.message || 'Bulk delete failed.');
    throw e;
  }
}
