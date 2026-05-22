# Customer REST API

## JSON envelope

**Success:**
```json
{ "status": "success", "code": 200, "message": "optional", "data": { }, "meta": { } }
```

**Error:**
```json
{ "status": "error", "code": 400, "message": "Human-readable message", "error": "same as message", "violations": { } }
```

Authenticate protected routes: `Authorization: Bearer <JWT>` from `POST /api/login` with `{ "email", "password" }`.

**Roles:** Customers use `ROLE_CUSTOMER` (inherits `ROLE_USER`). Staff/admin cannot access customer cart/order/payment routes (403).

---

## Public catalog

| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/products` | List products with `stock`, `inventoryStock`, `inStock` |
| GET | `/api/products/{id}` | Product detail |
| GET | `/api/customer/products` | Same catalog (alias) |

---

## Profile (`ROLE_CUSTOMER`)

| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/customer/profile` | Current user profile |

---

## Cart (`ROLE_CUSTOMER`)

| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/cart` or `/api/customer/cart` | Get cart |
| POST | `/api/cart/items` or `/api/customer/cart/items` | Add `{ "productId": 1, "quantity": 2 }` — **400** `{"error":"This item is currently out of stock."}` when `stock <= 0` |
| DELETE | `/api/cart/items/{id}` or `/api/customer/cart/items/{id}` | Remove line (optimized DQL delete) |
| PUT | `/api/customer/cart/items/{id}` | Update quantity |
| DELETE | `/api/customer/cart` | Clear cart |

---

## Orders (`ROLE_CUSTOMER`)

| Method | Path | Description |
|--------|------|-------------|
| POST | `/api/orders` or `/api/customer/orders` | Checkout cart `{ "notes": "optional" }` |
| GET | `/api/customer/orders` | List orders |
| GET | `/api/customer/orders/{id}` | Order detail |
| DELETE | `/api/customer/orders/{id}` | Cancel (restores stock) |

---

## Payments (`ROLE_CUSTOMER`)

| Method | Path | Description |
|--------|------|-------------|
| POST | `/api/payments` | Pay `{ "orderId": 1, "method": "gcash", "reference": "TXN-123" }` |
| POST | `/api/customer/orders/{id}/payment` | Pay by order id in URL |
| GET | `/api/customer/orders/{id}/payment` | Payment status |

Payment records are stored in the `payment` table (`transaction_reference`, `status`, `method`, `amount`).

---

## Mobile flow

1. `GET /api/products`
2. `POST /api/cart/items`
3. `POST /api/orders`
4. `POST /api/payments`
5. `GET /api/customer/profile`

## Related docs

- [POSTMAN_CHECKLIST.md](POSTMAN_CHECKLIST.md) — step-by-step manual tests
- [DEMO_SCRIPT.md](DEMO_SCRIPT.md) — presentation walkthrough
- [postman/Customer_API.postman_collection.json](postman/Customer_API.postman_collection.json) — import into Postman
- Project [README.md](../README.md) — installation and `app:api:verify-customer` command

## Cart timeout fix

Cart mutations avoid `cascade: ['remove']` + `orphanRemoval` together and do not null the owning side on delete (which caused Doctrine `UnitOfWork` infinite loops). Checkout clears cart items via bulk DQL `DELETE`.
