import { apiFetch } from '../utils/apiFetch.js';

export const ConfigService = {
  async exportConfig() {
    const response = await apiFetch({ path: '/config/export' });
    const data = response?.data ?? response;
    return data;
  },

  async downloadConfig() {
    const data = await this.exportConfig();
    const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `wp2-config-${new Date().toISOString().replace(/[:.]/g, '-')}.json`;
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
  },

  async importConfig(file) {
    const text = await file.text();
    const payload = JSON.parse(text);
    return apiFetch({ path: '/config/import', method: 'POST', data: { payload } });
  }
};
