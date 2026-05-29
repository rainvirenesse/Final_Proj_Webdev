# CREDO Realtime WebSocket Hub

Node.js fan-out server used by the Symfony backend for live dashboard and mobile notifications.

## Environment

| Variable | Description |
|----------|-------------|
| `PORT` / `REALTIME_WS_PORT` | Listen port (default `8090`) |
| `REALTIME_WS_BROADCAST_KEY` | Shared secret with Symfony (`REALTIME_WS_BROADCAST_KEY`) |

Symfony also needs:

- `REALTIME_WS_BROADCAST_URL` — e.g. `http://realtime-ws:8090/broadcast`
- `REALTIME_WS_PUBLIC_URL` — e.g. `ws://localhost:8090` (browser/mobile clients)

## Local (Docker Compose)

```bash
docker compose up -d realtime-ws app
```

## Railway

Deploy as a separate service from `realtime-ws/`, set the same `REALTIME_WS_BROADCAST_KEY` on both the hub and the PHP app, and point `REALTIME_WS_BROADCAST_URL` at the hub’s internal URL.

## Protocol

1. Client obtains `subscribeToken` from `GET /api/customer/realtime/token` (JWT) or `GET /realtime/token` (staff session).
2. Client connects: `ws://host:8090?token=<subscribeToken>`.
3. Symfony POSTs events to `/broadcast` with `X-Broadcast-Key` and JSON `{ type, payload, channels, sentAt }`.
