# Final presentation — demo script (5–8 minutes)

Use this script for criteria **Customer API**, **Auth**, **RBAC**, **Mobile ↔ Web sync**, and **Error handling**.

**Before you start**

- Server: `symfony server:start --host=0.0.0.0 --port=8000`
- Migrations + fixtures loaded
- Postman collection ready (`docs/POSTMAN_CHECKLIST.md`)
- Optional: phone with React Native app on same Wi‑Fi
- Run once: `php bin/console app:api:verify-customer --base-url=http://YOUR_IP:8000`

---

## 1. Introduction (30 sec)

> “Our system has three roles: **Customer** (mobile API), **Staff** and **Admin** (web dashboard). All data lives in one database so mobile and web stay in sync.”

Show architecture briefly:

- Symfony backend
- MySQL database
- React Native app → Customer API
- Browser → Admin panel

---

## 2. Customer API — five areas (2 min)

Open Postman (or terminal).

| Step | Action | What to say |
|------|--------|-------------|
| 1 | `GET /api/products` | “Public catalog with stock and images — no login required.” |
| 2 | `POST /api/login` (customer) | “JWT authentication for customers.” |
| 3 | `GET /api/customer/profile` | “Protected profile with Bearer token.” |
| 4 | `POST /api/cart/items` | “Add to cart; validates stock server-side.” |
| 5 | `POST /api/orders` | “Checkout creates order — Pending, Unpaid.” |
| 6 | `POST /api/payments` | “Payment moves order to In Progress, Paid.” |

Point out JSON shape: `status`, `code`, `data`.

---

## 3. RBAC (1 min)

> “Staff cannot use the customer mobile API.”

1. Login as **staff** via `POST /api/login`
2. `POST /api/cart/items` with staff token → **403** JSON
3. Login as **customer** again → cart works

Show web: staff at `/admin/dashboard`, customer has no admin access.

---

## 4. Validation & errors (1 min)

| Test | Result |
|------|--------|
| Add out-of-stock product | `400` — “This item is currently out of stock.” |
| Cart without token | `401` |
| Invalid product id | `404` with helpful message |

> “API returns proper HTTP codes, not HTML error pages.”

---

## 5. Mobile + web synchronization (1–2 min)

**Option A — React Native app**

1. Open app → product list with images
2. Login → add to cart → checkout → pay
3. Switch to browser → **Admin → Orders** → same order

**Option B — Postman only**

1. Place order in Postman
2. Refresh admin Orders list — order appears
3. Change product stock in admin → refresh `GET /api/products` — `stock` updated

> “Single database; changes are visible immediately after save.”

---

## 6. Database & business rules (30 sec)

Mention briefly:

- Products: stock auto-updates status (0 → Out of Stock, restock → Active)
- Orders: Pending → Unpaid; paid → In Progress
- Stock decreases on checkout, restores on cancel

---

## 7. Q&A backup commands

```bash
php bin/console debug:router | findstr api_
php bin/console doctrine:mapping:info
php bin/console app:api:verify-customer --base-url=http://192.168.1.20:8000
```

Docs: `README.md`, `docs/API_CUSTOMER.md`, `docs/POSTMAN_CHECKLIST.md`

---

## Rubric mapping (for instructor)

| Criterion | Demo section |
|-----------|----------------|
| 1 Mobile integration | §5 Option A |
| 2 Customer API | §2 |
| 3 Auth & security | §2 step 2, §4 token errors |
| 4 RBAC | §3 |
| 5 Mobile & web sync | §5 |
| 6 Database | §6 |
| 7 Error handling | §4 |
| 8 UI/UX | Web admin + mobile screens |
| 9 Stability | Pre-run verify command |
| 10 Documentation | README + docs folder |
