# Mercure real-time orders

## Docker

```bash
docker compose up -d mercure
```

Hub: `http://127.0.0.1:9090/.well-known/mercure`

## Symfony `.env`

```env
APP_URL=http://127.0.0.1:8000
MERCURE_URL=http://127.0.0.1:9090/.well-known/mercure
MERCURE_PUBLIC_URL=http://127.0.0.1:9090/.well-known/mercure
MERCURE_JWT_SECRET="!ChangeThisMercureHubJWTSecretKey!"
```

When Symfony runs **inside** Docker on the same network:

```env
MERCURE_URL=http://mercure/.well-known/mercure
MERCURE_PUBLIC_URL=http://127.0.0.1:9090/.well-known/mercure
```

For LAN mobile devices, set `MERCURE_PUBLIC_URL` and `APP_URL` to your machine IP (e.g. `http://192.168.1.3:9090/.well-known/mercure`).

`MERCURE_JWT_SECRET` must match `MERCURE_PUBLISHER_JWT_KEY` / `MERCURE_SUBSCRIBER_JWT_KEY` in `docker-compose.yaml`.

## CORS (React `localhost:3000`)

Configured on the Mercure hub via `MERCURE_EXTRA_DIRECTIVES` → `cors_origins` in `docker-compose.yaml`.

React Native uses `fetch` + `Authorization` (not browser EventSource), so CORS does not apply to the app; use a reachable `MERCURE_PUBLIC_URL` on the device network.

## API

| Action | Endpoint |
|--------|----------|
| Place order | `POST /api/orders` (JWT customer) |
| Approve order | `POST /api/orders/{id}/approve` (JWT staff) |
| Staff Mercure JWT | `GET /api/mercure/token` |
| Customer status JWT | `GET /api/customer/mercure/token/{orderId}` |

## Topics

| Topic | Event |
|-------|--------|
| `{APP_URL}/orders` | New order payload (`order.created`) |
| `{APP_URL}/orders/{id}/status` | `{ "status": "ready_to_deliver", "orderId": n }` |

## Frontends

- React dashboard example: `app-integration/react-dashboard/MercureOrdersDashboard.tsx`
- React Native: `CREDO/src/hooks/useOrderStatusMercure.ts`

Install in CREDO:

```bash
npm install @microsoft/fetch-event-source
```
