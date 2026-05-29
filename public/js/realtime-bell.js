/**
 * Notification bell — synced from /realtime/poll pending orders.
 */
(function () {
  const STORAGE_KEY = 'credo_realtime_bell_state_v1';

  function byId(id) {
    return document.getElementById(id);
  }

  function formatItemsSummary(items) {
    if (!Array.isArray(items) || !items.length) return '';
    return items.map((i) => `${i.quantity ?? 1}× ${i.name ?? 'Product'}`).join(', ');
  }

  function formatBody(n) {
    const who = n.customerName ?? 'Customer';
    const items = formatItemsSummary(n.items);
    const total = n.total ?? n.totalPrice;
    const totalStr = total != null ? ` · ₱${Number(total).toFixed(2)}` : '';
    const num = n.orderNumber ? ` (${n.orderNumber})` : '';
    return `${who}${num}: ${items}${totalStr}`;
  }

  function formatTime(iso) {
    if (!iso) return '';
    try {
      return new Date(iso).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    } catch {
      return '';
    }
  }

  async function approveOrder(orderId) {
    const res = await fetch(`/realtime/orders/${orderId}/approve`, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { Accept: 'application/json' },
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok || data.success === false) {
      throw new Error(data.error ?? 'Approve failed');
    }
    document.dispatchEvent(
      new CustomEvent('credo:realtime', {
        detail: { type: 'order.status_changed', payload: data.order ?? { orderId } },
      }),
    );
    document.dispatchEvent(new CustomEvent('credo:refresh-poll'));
  }

  function renderList(pending) {
    const badge = byId('realtime-bell-badge');
    const list = byId('realtime-bell-list');
    if (!badge || !list) return;

    const items = Array.isArray(pending) ? pending : [];
    const unread = items.length;

    if (unread > 0) {
      badge.textContent = String(unread > 99 ? '99+' : unread);
      badge.classList.remove('d-none');
    } else {
      badge.classList.add('d-none');
    }

    list.innerHTML = '';
    if (!items.length) {
      const empty = document.createElement('div');
      empty.className = 'list-group-item text-muted small';
      empty.textContent = 'No orders waiting for approval.';
      list.appendChild(empty);
      return;
    }

    items.forEach((n) => {
      const row = document.createElement('div');
      row.className = 'list-group-item';
      row.innerHTML = `
        <div class="d-flex w-100 justify-content-between gap-2">
          <strong class="small">New mobile order</strong>
          <small class="text-muted">${formatTime(n.orderedAt)}</small>
        </div>
        <div class="small text-muted mt-1">${formatBody(n)}</div>
      `;

      if (n.canApprove !== false && n.orderId) {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-sm btn-primary mt-2';
        btn.textContent = 'Approve';
        btn.addEventListener('click', async (e) => {
          e.preventDefault();
          e.stopPropagation();
          btn.disabled = true;
          btn.textContent = 'Approving…';
          try {
            await approveOrder(n.orderId);
            btn.textContent = 'Approved';
            btn.classList.replace('btn-primary', 'btn-success');
            row.classList.add('opacity-50');
            setTimeout(() => row.remove(), 800);
          } catch (err) {
            btn.disabled = false;
            btn.textContent = 'Approve';
            window.alert(err.message ?? 'Approve failed');
          }
        });
        row.appendChild(btn);
      }

      list.appendChild(row);
    });
  }

  async function fetchPendingFallback() {
    try {
      const res = await fetch('/realtime/poll', {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
      });
      if (!res.ok) return;
      const data = await res.json();
      const pending = Array.isArray(data.pending) ? data.pending : [];
      window.__CREDO_LAST_POLL__ = { pending, serverTime: data.serverTime };
      renderList(pending);
    } catch {
      /* notifications.js handles errors */
    }
  }

  function init() {
    const bell = byId('realtime-bell');
    if (!bell) return;

    window.CredoRealtimeBell = { render: renderList };

    const last = window.__CREDO_LAST_POLL__;
    if (last?.pending) {
      renderList(last.pending);
    } else {
      void fetchPendingFallback();
    }

    document.addEventListener('credo:poll', (e) => {
      renderList(e?.detail?.pending ?? []);
    });

    byId('realtime-bell-clear')?.addEventListener('click', () => {
      renderList([]);
      const badge = byId('realtime-bell-badge');
      badge?.classList.add('d-none');
    });

    bell.addEventListener('show.bs.dropdown', () => {
      const badge = byId('realtime-bell-badge');
      badge?.classList.add('d-none');
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
