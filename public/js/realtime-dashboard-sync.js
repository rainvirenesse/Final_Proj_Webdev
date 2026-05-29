/**
 * Extra dashboard refresh on poll (stats handled in realtime-notifications.js too).
 */
(function () {
  document.addEventListener('credo:poll', (e) => {
    const pending = e?.detail?.pending ?? [];
    if (document.body?.dataset?.route !== 'admin_dashboard') return;

    pending.forEach((n) => {
      document.dispatchEvent(new CustomEvent('credo:realtime', { detail: { type: 'order.created', payload: n } }));
    });
  });
})();
