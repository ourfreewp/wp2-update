import { createContext } from '@lit/context';
import { PackageService } from '../services/PackageService.js';
import { AppService } from '../services/AppService.js';

export const packageServiceContext = createContext('package-service', new PackageService());
export const appServiceContext = createContext('app-service', new AppService());
