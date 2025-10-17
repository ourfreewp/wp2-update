import { logger } from './logger.js';

/**
 * Asserts that a condition is truthy. If not, it logs an error.
 * @param {*} condition - The condition to check.
 * @param {string} message - The message to log if the assertion fails.
 */
export function assert(condition, message) {
  if (!condition) {
    logger.error(`Assertion Failed: ${message}`);
    // You can optionally throw an error in development to halt execution
    // if (window.wp2UpdateData?.debugMode) {
    //   throw new Error(`Assertion Failed: ${message}`);
    // }
  }
}