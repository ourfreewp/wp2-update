// Utility for formatting dates, times, and numbers using Intl API
export function formatDate(date, locale = navigator.language, options = {}) {
  return new Intl.DateTimeFormat(locale, options).format(new Date(date));
}

export function formatTime(date, locale = navigator.language, options = {}) {
  return new Intl.DateTimeFormat(locale, { ...options, hour: '2-digit', minute: '2-digit' }).format(new Date(date));
}

export function formatNumber(number, locale = navigator.language, options = {}) {
  return new Intl.NumberFormat(locale, options).format(number);
}
