import { apiRequest } from './client';

export type RealtimeConfig = {
  websocketUrl: string;
  enabled: boolean;
  events?: string[];
};

export type RealtimeToken = {
  subscribeToken: string;
  expiresIn: number;
};

export type RealtimeMessage = {
  type: string;
  payload: Record<string, unknown>;
  sentAt?: string;
};

export type RealtimeHandler = (message: RealtimeMessage) => void;

export async function fetchRealtimeConfig(token: string): Promise<RealtimeConfig> {
  return apiRequest<RealtimeConfig>('/api/customer/realtime/config', {}, token);
}

export async function fetchRealtimeToken(token: string): Promise<RealtimeToken> {
  return apiRequest<RealtimeToken>('/api/customer/realtime/token', {}, token);
}

/**
 * Maintains a JWT-authenticated WebSocket connection to the CREDO realtime hub.
 */
export class RealtimeClient {
  private socket: WebSocket | null = null;
  private reconnectTimer: ReturnType<typeof setTimeout> | null = null;
  private refreshTimer: ReturnType<typeof setInterval> | null = null;
  private handlers = new Set<RealtimeHandler>();
  private closed = false;

  constructor(private readonly getJwt: () => string | null | Promise<string | null>) {}

  onMessage(handler: RealtimeHandler): () => void {
    this.handlers.add(handler);
    return () => this.handlers.delete(handler);
  }

  async connect(): Promise<void> {
    this.closed = false;
    await this.openSocket();
    this.refreshTimer = setInterval(() => {
      void this.openSocket();
    }, 50 * 60 * 1000);
  }

  disconnect(): void {
    this.closed = true;
    if (this.reconnectTimer) {
      clearTimeout(this.reconnectTimer);
    }
    if (this.refreshTimer) {
      clearInterval(this.refreshTimer);
    }
    this.socket?.close();
    this.socket = null;
  }

  private async openSocket(): Promise<void> {
    const jwt = await this.getJwt();
    if (!jwt || this.closed) {
      return;
    }

    const config = await fetchRealtimeConfig(jwt);
    if (!config.enabled || !config.websocketUrl) {
      return;
    }

    const { subscribeToken } = await fetchRealtimeToken(jwt);
    const separator = config.websocketUrl.includes('?') ? '&' : '?';
    const url = `${config.websocketUrl}${separator}token=${encodeURIComponent(subscribeToken)}`;

    if (this.socket) {
      this.socket.close();
    }

    const ws = new WebSocket(url);
    this.socket = ws;

    ws.onmessage = (event) => {
      try {
        const data = JSON.parse(String(event.data)) as RealtimeMessage;
        if (data.type === 'connected') {
          return;
        }
        this.handlers.forEach((handler) => handler(data));
      } catch {
        /* ignore */
      }
    };

    ws.onclose = () => {
      if (!this.closed) {
        this.reconnectTimer = setTimeout(() => void this.openSocket(), 5000);
      }
    };
  }
}

export function notificationTitle(message: RealtimeMessage): string {
  switch (message.type) {
    case 'order.created':
      return 'New order';
    case 'order.status_changed':
      return 'Order updated';
    case 'payment.completed':
      return 'Payment received';
    case 'stock.updated':
      return 'Stock update';
    case 'products.updated':
      return 'Catalog updated';
    case 'cart.updated':
      return 'Cart updated';
    case 'orders.updated':
      return 'Orders updated';
    default:
      return 'Notification';
  }
}

export function notificationBody(message: RealtimeMessage): string {
  const p = message.payload;
  switch (message.type) {
    case 'order.created':
    case 'order.status_changed':
    case 'payment.completed':
      return `${String(p.orderNumber ?? 'Order')} · ${String(p.status ?? '')}`;
    case 'stock.updated':
      return `${String(p.name ?? 'Product')} · ${String(p.stock ?? '')} in stock`;
    default:
      return message.type;
  }
}
