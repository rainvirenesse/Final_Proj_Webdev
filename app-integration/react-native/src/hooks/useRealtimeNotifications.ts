import { useEffect, useRef, useState } from 'react';
import {
  RealtimeClient,
  RealtimeMessage,
  notificationBody,
  notificationTitle,
} from '../api/realtime';

export type RealtimeNotification = {
  id: string;
  type: string;
  title: string;
  body: string;
  payload: Record<string, unknown>;
  receivedAt: string;
};

type Options = {
  enabled?: boolean;
  maxItems?: number;
};

/**
 * Subscribe to backend realtime events when the user is logged in.
 */
export function useRealtimeNotifications(
  getJwt: () => string | null | Promise<string | null>,
  options: Options = {},
) {
  const { enabled = true, maxItems = 20 } = options;
  const [notifications, setNotifications] = useState<RealtimeNotification[]>([]);
  const clientRef = useRef<RealtimeClient | null>(null);

  useEffect(() => {
    if (!enabled) {
      return undefined;
    }

    const client = new RealtimeClient(getJwt);
    clientRef.current = client;

    const unsubscribe = client.onMessage((message: RealtimeMessage) => {
      const item: RealtimeNotification = {
        id: `${message.type}-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
        type: message.type,
        title: notificationTitle(message),
        body: notificationBody(message),
        payload: message.payload,
        receivedAt: new Date().toISOString(),
      };
      setNotifications((prev) => [item, ...prev].slice(0, maxItems));
    });

    void client.connect();

    return () => {
      unsubscribe();
      client.disconnect();
      clientRef.current = null;
    };
  }, [enabled, getJwt, maxItems]);

  const clear = () => setNotifications([]);

  return { notifications, clear };
}
