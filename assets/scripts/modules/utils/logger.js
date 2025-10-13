/**
 * Standardized JavaScript Logger Utility
 */
const Logger = (() => {
  const isDebugMode = true; // Set this to false to disable debug logs

  const log = (level, ...args) => {
    if (isDebugMode) {
      const timestamp = new Date().toISOString();
      console[level](`[${timestamp}]`, ...args);
    }
  };

  return {
    debug: (...args) => log("log", "[DEBUG]", ...args),
    info: (...args) => log("info", "[INFO]", ...args),
    warn: (...args) => log("warn", "[WARN]", ...args),
    error: (...args) => log("error", "[ERROR]", ...args),
  };
})();

export const logger = Logger;
