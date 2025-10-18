// Focus trap directive for modals
export function focusTrap(modalElement) {
  const focusableSelectors = [
    'a[href]', 'button:not([disabled])', 'textarea:not([disabled])',
    'input:not([disabled])', 'select:not([disabled])', '[tabindex]:not([tabindex="-1"])'
  ];
  const getFocusable = () => Array.from(modalElement.querySelectorAll(focusableSelectors.join(',')));

  function trap(e) {
    const focusable = getFocusable();
    if (!focusable.length) return;
    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    if (e.key === 'Tab') {
      if (e.shiftKey && document.activeElement === first) {
        e.preventDefault();
        last.focus();
      } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault();
        first.focus();
      }
    }
  }

  modalElement.addEventListener('keydown', trap);
  // Focus first element on open
  setTimeout(() => {
    const focusable = getFocusable();
    if (focusable.length) focusable[0].focus();
  }, 0);

  return () => modalElement.removeEventListener('keydown', trap);
}
