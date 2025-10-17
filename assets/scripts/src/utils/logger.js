/**
 * Standardized JavaScript Logger Utility
 */
const Logger = (() => {
  // Respect the debug flag from the backend
  const isDebugMode = window.wp2UpdateData?.debugMode || false;

  // Include correlation ID in logger context
  const log = (level, message, context = {}) => {
    // Only log if debug mode is enabled
    if (!isDebugMode) {
      return;
    }

    const timestamp = new Date().toISOString();
    const correlationId = window.wp2UpdateCorrelationId || 'N/A';
    const contextString = JSON.stringify({ ...context, correlationId });

    // The console method (log, info, warn, error) is passed as 'level'
    console[level](`[${timestamp}] [WP2-UPDATE] ${message}`, contextString);
  };

  return {
    debug: (message, context) => log("log", message, context),
    info: (message, context) => log("info", message, context),
    warn: (message, context) => log("warn", message, context),
    error: (message, context) => log("error", message, context),
  };
})();

export const logger = Logger;
