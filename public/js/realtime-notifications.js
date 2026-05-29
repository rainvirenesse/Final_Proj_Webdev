/**
 * Admin/staff: HTTP polling for orders + dashboard stats (no WebSocket).
 */
(function () {
  const CONTAINER_ID = 'realtime-notifications';
  const MAX_TOASTS = 5;
  const POLL_MS = 3000;
  const TOAST_SEEN_KEY = 'credo_poll_toast_seen_v1';

  const EVENT_LABELS = {
    'order.created': { title: 'New mobile order', icon: 'bi-bag-plus', variant: 'primary' },
  };

  let pollSince = null;
  let pollTimer = null;
  let toastSeenIds = loadToastSeen();

  function loadToastSeen() {
    try {
      const raw = sessionStorage.getItem(TOAST_SEEN_KEY);
      const arr = raw ? JSON.parse(raw) : [];
      return new Set(Array.isArray(arr) ? arr.map(Number) : []);
    } catch {
      return new Set();
    }
  }

  function saveToastSeen() {
    try {
      sessionStorage.setItem(TOAST_SEEN_KEY, JSON.stringify([...toastSeenIds].slice(-200)));
    } catch {
      /* ignore */
    }
  }

  function ensureContainer() {
    let el = document.getElementById(CONTAINER_ID);
    if (!el) {
      el = document.createElement('div');
      el.id = CONTAINER_ID;
      el.className = 'position-fixed top-0 end-0 p-3';
      el.style.zIndex = '2000';
      document.body.appendChild(el);
    }
    return el;
  }

  function setBellStatus(text, ok) {
    const el = document.getElementById('realtime-bell-status');
    if (!el) return;
    el.textContent = text;
    el.className = 'small px-3 py-1 border-bottom ' + (ok ? 'text-success' : 'text-warning');
  }

  function formatItemsSummary(items) {
    if (!Array.isArray(items) || !items.length) return 'items';
    return items.map((i) => `${i.quantity ?? 1}× ${i.name ?? 'Product'}`).join(', ');
  }

  function orderMessage(payload) {
    const who = payload.customerName ?? 'Customer';
    const items = formatItemsSummary(payload.items);
    const total = payload.total ?? payload.totalPrice;
    const totalStr = total != null ? ` · Total ₱${Number(total).toFixed(2)}` : '';
    return `${who}: ${items}${totalStr}`;
  }

  function emitRealtime(type, payload) {
    document.dispatchEvent(new CustomEvent('credo:realtime', { detail: { type, payload } }));
  }

  function emitPoll(data) {
    window.__CREDO_LAST_POLL__ = data;
    if (typeof window.CredoRealtimeBell?.render === 'function') {
      window.CredoRealtimeBell.render(data.pending ?? []);
    }
    document.dispatchEvent(new CustomEvent('credo:poll', { detail: data }));
  }

  async function approveOrder(orderId, onDone) {
    const res = await fetch(`/realtime/orders/${orderId}/approve`, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { Accept: 'application/json' },
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok || data.success === false) {
      throw new Error(data.error ?? 'Could not approve order.');
    }
    if (typeof onDone === 'function') onDone();
    emitRealtime('order.status_changed', data.order ?? { orderId, status: 'IN_PROGRESS' });
  }

  function showToast(payload) {
    const meta = EVENT_LABELS['order.created'];
    const container = ensureContainer();
    const toast = document.createElement('div');
    toast.className = `toast show align-items-center text-bg-${meta.variant} border-0 mb-2`;

    const orderId = payload.orderId;
    const canApprove = orderId && payload.canApprove !== false;

    toast.innerHTML = `
      <div class="d-flex flex-column w-100">
        <div class="d-flex">
          <div class="toast-body flex-grow-1">
            <i class="bi ${meta.icon} me-2"></i>
            <strong>${meta.title}</strong><br>
            <small>${orderMessage(payload)}</small>
          </div>
          <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
        ${canApprove ? '<div class="px-3 pb-2"><button type="button" class="btn btn-sm btn-light realtime-approve-btn">Approve</button></div>' : ''}
      </div>`;

    container.prepend(toast);
    while (container.children.length > MAX_TOASTS) container.lastChild.remove();

    toast.querySelector('button[data-bs-dismiss="toast"]')?.addEventListener('click', () => toast.remove());

    const approveBtn = toast.querySelector('.realtime-approve-btn');
    if (approveBtn) {
      approveBtn.addEventListener('click', async () => {
        approveBtn.disabled = true;
        approveBtn.textContent = 'Approving…';
        try {
          await approveOrder(orderId, () => {
            toast.remove();
            void pollOnce();
          });
        } catch (e) {
          approveBtn.disabled = false;
          approveBtn.textContent = 'Approve';
          window.alert(e.message ?? 'Approve failed');
        }
      });
    }

    setTimeout(() => toast.remove(), 25000);
    emitRealtime('order.created', payload);
  }

  function applyDashboardStats(stats) {
    if (!stats || document.body?.dataset?.route !== 'admin_dashboard') return;
    const map = {
      'stat-total-users': stats.totalUsers,
      'stat-total-staff': stats.totalStaff,
      'stat-active-orders': stats.activeOrders,
      'stat-completed-orders': stats.completedOrders,
      'stat-total-products': stats.totalProducts,
      'stat-active-products': stats.activeProducts,
      'stat-total-stock': stats.totalProductStock,
      'stat-total-services': stats.totalServices,
      'stat-total-orders': stats.totalOrders,
    };
    Object.entries(map).forEach(([id, val]) => {
      const el = document.getElementById(id);
      if (el && val != null) el.textContent = String(val);
    });
  }

  function prependDashboardOrder(payload) {
    if (document.body?.dataset?.route !== 'admin_dashboard') return;
    const tbody = document.querySelector('#ordersTable tbody');
    if (!tbody || !payload.orderNumber) return;

    const exists = Array.from(tbody.querySelectorAll('tr')).some((tr) =>
      tr.textContent?.includes(String(payload.orderNumber)),
    );
    if (exists) return;

    const tr = document.createElement('tr');
    tr.className = 'table-warning';
    const status = payload.status ?? 'PENDING';
    const badgeClass =
      status === 'COMPLETED' ? 'badge-admin-success' : status === 'IN_PROGRESS' ? 'badge-admin-warning' : 'badge-admin-info';
    tr.innerHTML = `
      <td>${payload.orderNumber}</td>
      <td>${payload.customerName ?? 'Customer'}</td>
      <td>₱${Number(payload.total ?? payload.totalPrice ?? 0).toFixed(2)}</td>
      <td><span class="badge ${badgeClass}">${status}</span></td>
      <td>${new Date().toISOString().slice(0, 10)}</td>`;
    tbody.prepend(tr);
    setTimeout(() => tr.classList.remove('table-warning'), 5000);
  }

  function handlePollResponse(data) {
    const pending = Array.isArray(data.pending) ? data.pending : [];
    const newSince = Array.isArray(data.newSince) ? data.newSince : pending;

    emitPoll({ pending, serverTime: data.serverTime });
    applyDashboardStats(data.stats);

    newSince.forEach((n) => {
      const orderId = Number(n.orderId);
      if (!orderId || toastSeenIds.has(orderId)) return;
      toastSeenIds.add(orderId);
      saveToastSeen();
      showToast(n);
      prependDashboardOrder(n);
    });

    const count = pending.length;
    setBellStatus(
      count > 0 ? `Live · ${count} awaiting approval` : `Live (polling every ${POLL_MS / 1000}s)`,
      true,
    );
  }

  async function pollOnce() {
    try {
      const url = pollSince
        ? `/realtime/poll?since=${encodeURIComponent(pollSince)}`
        : '/realtime/poll';
      const res = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
      if (res.status === 401 || res.status === 403) {
        setBellStatus('Log in as staff to receive orders', false);
        return;
      }
      if (!res.ok) {
        setBellStatus(`Poll error (${res.status})`, false);
        return;
      }
      const data = await res.json();
      if (data.serverTime) pollSince = data.serverTime;
      handlePollResponse(data);
    } catch (e) {
      setBellStatus('Poll connection error', false);
      console.warn('[realtime-poll]', e);
    }
  }

  function startPolling() {
    if (pollTimer) clearInterval(pollTimer);
    pollSince = null;
    setBellStatus('Connecting…', false);
    void pollOnce();
    pollTimer = window.setInterval(() => void pollOnce(), POLL_MS);
    document.addEventListener('credo:refresh-poll', () => void pollOnce());
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', startPolling);
  } else {
    startPolling();
  }
})();
