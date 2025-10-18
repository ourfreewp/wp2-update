import { atom } from 'nanostores';

export const apps = atom([]);

export const updateAppState = (appId, newAppState) => {
  apps.set(
    apps.get().map((app) =>
      app.id === appId ? { ...app, ...newAppState } : app
    )
  );
};