import { useCallback, useEffect, useRef, useState } from 'react';

/**
 * Staff dashboard: live orders + stock via Mercure (EventSource).
 *
 * Env (Create React App example):
 *   REACT_APP_API_URL=http://127.0.0.1:8000
 *   REACT_APP_MERCURE_URL=http://127.0.0.1:9090/.well-known/mercure
 *
 * Requires a staff JWT in localStorage key `staff_jwt` (login via /api/login).
 */

const API_URL = process.env.REACT_APP_API_URL ?? 'http://127.0.0.1:8000';
const MERCURE_HUB = process.env.REACT_APP_MERCURE_URL ?? 'http://127.0.0.1:9090/.well-known/mercure';

export type OrderItem = {
  id?: number;
  name: string;
  quantity: number;
  price: number;
  lineTotal: number;
  productId?: number;
};

export type OrderRow = {
  orderId: number;
  orderNumber: string;
  customerName: string;
  items: OrderItem[];
  total: number;
  status: string;
};

export type StockRow = {
  productId: number;
  name: string;
  stock: number;
};

type MercureOrderEvent = {
  event: 'order.created';
  orderId: number;
  orderNumber: string;
  customerName: string;
  items: OrderItem[];
  total: number;
  status: string;
  stockUpdates?: StockRow[];
};

type Toast = {
  id: string;
  orderId: number;
  title: string;
  body: string;
};

function getStaffJwt(): string | null {
  return localStorage.getItem('staff_jwt');
}

async function fetchMercureToken(): Promise<{ token: string; topic: string }> {
  const jwt = getStaffJwt();
  if (!jwt) {
    throw new Error('Not logged in (staff_jwt missing).');
  }
  const res = await fetch(`${API_URL}/api/mercure/token`, {
    headers: { Authorization: `Bearer ${jwt}` },
  });
  if (!res.ok) {
    throw new Error(`Mercure token failed (${res.status})`);
  }
  return res.json();
}

async function approveOrder(orderId: number): Promise<void> {
  const jwt = getStaffJwt();
  if (!jwt) {
    throw new Error('Not logged in.');
  }
  const res = await fetch(`${API_URL}/api/orders/${orderId}/approve`, {
    method: 'POST',
    headers: { Authorization: `Bearer ${jwt}` },
  });
  if (!res.ok) {
    const err = await res.json().catch(() => ({}));
    throw new Error(err?.message ?? `Approve failed (${res.status})`);
  }
}

export default function MercureOrdersDashboard() {
  const [orders, setOrders] = useState<OrderRow[]>([]);
  const [stock, setStock] = useState<Record<number, StockRow>>({});
  const [toasts, setToasts] = useState<Toast[]>([]);
  const [error, setError] = useState<string | null>(null);
  const sourceRef = useRef<EventSource | null>(null);

  const dismissToast = useCallback((id: string) => {
    setToasts((prev) => prev.filter((t) => t.id !== id));
  }, []);

  const onApprove = useCallback(
    async (orderId: number, toastId: string) => {
      try {
        await approveOrder(orderId);
        setOrders((prev) =>
          prev.map((o) =>
            o.orderId === orderId ? { ...o, status: 'IN_PROGRESS' } : o,
          ),
        );
        dismissToast(toastId);
      } catch (e) {
        setError(e instanceof Error ? e.message : 'Approve failed');
      }
    },
    [dismissToast],
  );

  useEffect(() => {
    let cancelled = false;

    async function connect() {
      try {
        const { token, topic } = await fetchMercureToken();
        if (cancelled) {
          return;
        }

        const url = new URL(MERCURE_HUB);
        url.searchParams.append('topic', topic);
        // EventSource cannot set Authorization; Mercure accepts subscriber JWT as query param.
        url.searchParams.append('authorization', token);
        const source = new EventSource(url.toString());
        sourceRef.current = source;

        source.onmessage = (event) => {
          const data = JSON.parse(event.data) as MercureOrderEvent;
          if (data.event !== 'order.created') {
            return;
          }

          const row: OrderRow = {
            orderId: data.orderId,
            orderNumber: data.orderNumber,
            customerName: data.customerName,
            items: data.items,
            total: data.total,
            status: data.status,
          };

          setOrders((prev) => {
            const exists = prev.some((o) => o.orderId === row.orderId);
            if (exists) {
              return prev.map((o) => (o.orderId === row.orderId ? row : o));
            }
            return [row, ...prev];
          });

          if (data.stockUpdates?.length) {
            setStock((prev) => {
              const next = { ...prev };
              for (const s of data.stockUpdates!) {
                next[s.productId] = s;
              }
              return next;
            });
          }

          const summary = data.items
            .map((i) => `${i.quantity}× ${i.name}`)
            .join(', ');
          const toastId = `order-${data.orderId}-${Date.now()}`;
          setToasts((prev) => [
            {
              id: toastId,
              orderId: data.orderId,
              title: `New order from ${data.customerName}`,
              body: `${data.orderNumber} · ${summary} · $${data.total.toFixed(2)}`,
            },
            ...prev,
          ]);
        };

        source.onerror = () => {
          setError('Mercure connection lost. Reconnecting…');
        };
      } catch (e) {
        setError(e instanceof Error ? e.message : 'Mercure setup failed');
      }
    }

    void connect();

    return () => {
      cancelled = true;
      sourceRef.current?.close();
      sourceRef.current = null;
    };
  }, []);

  return (
    <div style={{ fontFamily: 'system-ui', padding: 16, maxWidth: 960 }}>
      <h1>Orders (Mercure)</h1>
      {error && <p style={{ color: 'crimson' }}>{error}</p>}

      <section aria-label="Notifications" style={{ position: 'fixed', top: 16, right: 16, zIndex: 10 }}>
        {toasts.map((t) => (
          <div
            key={t.id}
            style={{
              background: '#1e293b',
              color: '#fff',
              padding: 12,
              marginBottom: 8,
              borderRadius: 8,
              minWidth: 280,
              boxShadow: '0 4px 12px rgba(0,0,0,.2)',
            }}
          >
            <strong>{t.title}</strong>
            <p style={{ margin: '6px 0' }}>{t.body}</p>
            <button type="button" onClick={() => void onApprove(t.orderId, t.id)}>
              Approve
            </button>{' '}
            <button type="button" onClick={() => dismissToast(t.id)}>
              Dismiss
            </button>
          </div>
        ))}
      </section>

      <h2>Live orders</h2>
      <ul>
        {orders.map((o) => (
          <li key={o.orderId}>
            <strong>{o.orderNumber}</strong> — {o.customerName} — {o.status} — ${o.total.toFixed(2)}
          </li>
        ))}
      </ul>

      <h2>Stock</h2>
      <ul>
        {Object.values(stock).map((s) => (
          <li key={s.productId}>
            {s.name}: {s.stock}
          </li>
        ))}
      </ul>
    </div>
  );
}
