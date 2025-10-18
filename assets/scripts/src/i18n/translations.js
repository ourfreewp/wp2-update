// Centralized translation strings for WP2 Update
import { __, _n, _x } from '@wordpress/i18n';

export const translations = {
  addPackage: __('Add Package', 'wp2-update'),
  noPackages: (count) => _n(
    'No package found. Click "Add Package" to create one.',
    'No packages found. Click "Add Package" to create one.',
    count,
    'wp2-update'
  ),
  addApp: __('Add App', 'wp2-update'),
  noApps: (count) => _n(
    'No app found. Click "Add App" to create one.',
    'No apps found. Click "Add App" to create one.',
    count,
    'wp2-update'
  ),
  sync: __('Sync', 'wp2-update'),
  update: __('Update', 'wp2-update'),
  assignApp: __('Assign App', 'wp2-update'),
  loadingReleases: __('Loading releases...', 'wp2-update'),
  updateTo: __('Update to...', 'wp2-update'),
  confirmUpdate: (pkg, tag) => _x(
    'Are you sure you want to update %1$s to %2$s?',
    'Confirmation message for updating package',
    'wp2-update'
  ).replace('%1$s', pkg).replace('%2$s', tag),
  // Add more strings as needed
};
