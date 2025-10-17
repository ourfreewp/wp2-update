import { logger } from './logger.js';

const timers = new Map();

/**
 * Client-side profiler for measuring performance of operations.
 */
export const profiler = {
  /**
   * Starts a timer.
   * @param {string} name - The unique name for the timer.
   */
  start(name) {
    timers.set(name, {
      start: performance.now(),
      laps: [],
    });
    logger.debug(`Timer started: ${name}`);
  },

  /**
   * Records a lap for an active timer.
   * @param {string} name - The name of the timer.
   */
  lap(name) {
    if (!timers.has(name)) return;
    const timer = timers.get(name);
    const lapTime = performance.now();
    const lastTime = timer.laps.length > 0 ? timer.laps[timer.laps.length - 1].time : timer.start;
    timer.laps.push({
      time: lapTime,
      duration: lapTime - lastTime,
    });
    logger.debug(`Timer lap: ${name}`, { duration_ms: lapTime - lastTime });
  },

  /**
   * Stops a timer and logs the total duration.
   * @param {string} name - The name of the timer to stop.
   */
  stop(name) {
    if (!timers.has(name)) return;
    const timer = timers.get(name);
    const duration = performance.now() - timer.start;
    logger.info(`Timer stopped: ${name}`, {
        total_duration_ms: duration,
        laps: timer.laps.length
    });
    timers.delete(name);
  },
};