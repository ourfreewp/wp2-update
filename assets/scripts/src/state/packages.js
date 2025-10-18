import { atom } from 'nanostores';

export const packages = atom([]);

export const updatePackageState = (packageId, newPackageState) => {
  packages.set(
    packages.get().map((pkg) =>
      pkg.id === packageId ? { ...pkg, ...newPackageState } : pkg
    )
  );
};