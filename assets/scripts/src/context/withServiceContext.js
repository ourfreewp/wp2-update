import { consume } from '@lit/context';
import { packageServiceContext, appServiceContext } from './ServiceContext.js';

export function withPackageService(component) {
  return consume(packageServiceContext)(component);
}

export function withAppService(component) {
  return consume(appServiceContext)(component);
}
