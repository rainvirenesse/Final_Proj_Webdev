# Customer REST API (`/api/customer`)

Standard JSON shape:

**Success:** `{ "data": { ... }, "meta": { ... } }` (meta optional)  
**Error:** `{ "error": "message", "code": 401, "violations": { ... } }` (violations on 422)

Authenticate protected routes with: `Authorization: Bearer <JWT>` (from `POST /api/login`).

---

## Products (public)

| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/customer/products` | List active products |
| GET | `/api/customer/products/{id}` | Product detail |

---

## Profile (JWT)

| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/customer/profile` | Current customer profile |
| PUT | `/api/customer/profile` | Update profile (`username`) |

---

## Cart (JWT)

| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/customer/cart` | Get cart |
| POST | `/api/customer/cart/items` | Add item `{ "productId": 1, "quantity": 2 }` |
| PUT | `/api/customer/cart/items/{id}` | Update quantity `{ "quantity": 3 }` |
| DELETE | `/api/customer/cart/items/{id}` | Remove line |
| DELETE | `/api/customer/cart` | Clear cart |

---

## Orders (JWT)

| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/customer/orders` | My orders |
| GET | `/api/customer/orders/{id}` | Order detail |
| POST | `/api/customer/orders` | Checkout cart `{ "notes": "optional" }` |
| PUT | `/api/customer/orders/{id}` | Update notes `{ "notes": "..." }` |
| DELETE | `/api/customer/orders/{id}` | Cancel order (restores stock) |

---

## Payments (JWT)

| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/customer/orders/{id}/payment` | Payment status |
| POST | `/api/customer/orders/{id}/payment` | Mark paid `{ "method": "gcash", "reference": "TXN-123" }` |

---

## Example flow (mobile)

1. `GET /api/customer/products` — browse catalog  
2. `POST /api/customer/cart/items` — add to cart  
3. `POST /api/customer/orders` — place order  
4. `POST /api/customer/orders/{id}/payment` — pay  
5. Admin dashboard shows the same order and updated stock
