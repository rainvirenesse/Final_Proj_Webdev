# Postman / API test checklist — Customer API

Base URL: `http://192.168.1.20:8000` (replace with your machine IP or `127.0.0.1:8000`).

Set collection variables:

| Variable | Example |
|----------|---------|
| `baseUrl` | `http://192.168.1.20:8000` |
| `token` | *(set after login)* |
| `productId` | *(set after products list)* |
| `cartItemId` | *(set after add to cart)* |
| `orderId` | *(set after checkout)* |

---

## 1. Products (public — no auth)

### 1.1 List products

- **GET** `{{baseUrl}}/api/products`
- **Headers:** `Accept: application/json`
- **Expected:** `200`, `status: success`, `data[]` with `id`, `name`, `stock`, `inventoryStock`, `inStock`, `imageUrl`
- **Save:** `productId` = first item where `inStock: true`

### 1.2 Product detail

- **GET** `{{baseUrl}}/api/products/{{productId}}`
- **Expected:** `200`, single product in `data`

---

## 2. Authentication

### 2.1 Customer login

- **POST** `{{baseUrl}}/api/login`
- **Body (JSON):**

```json
{
  "email": "john.doe@example.com",
  "password": "customer123"
}
```

- **Expected:** `200`, body contains `token`
- **Save:** `token` for Bearer auth

### 2.2 Staff login (should fail customer cart later)

- **POST** `{{baseUrl}}/api/login`
- **Body:** staff/admin credentials from fixtures
- **Expected:** `200` with token, but `roles` includes `ROLE_STAFF` or `ROLE_ADMIN`

---

## 3. Profile (customer JWT)

### 3.1 Get profile

- **GET** `{{baseUrl}}/api/customer/profile`
- **Headers:** `Authorization: Bearer {{token}}`, `Accept: application/json`
- **Expected:** `200`, `data.email`, `data.roles` includes `ROLE_CUSTOMER`

### 3.2 Staff on profile (RBAC)

- Use staff token from 2.2
- **GET** `{{baseUrl}}/api/customer/profile`
- **Expected:** `403` JSON (not HTML)

---

## 4. Cart

### 4.1 Add to cart

- **POST** `{{baseUrl}}/api/cart/items`
- **Headers:** `Authorization: Bearer {{token}}`, `Content-Type: application/json`
- **Body:**

```json
{
  "productId": {{productId}},
  "quantity": 1
}
```

- **Expected:** `201`, `data.items` includes product
- **Save:** `cartItemId` from `data.items[0].id`

### 4.2 Out of stock (edge case)

- Use a product with `stock: 0` or `inStock: false`
- **POST** `{{baseUrl}}/api/cart/items` with that `productId`
- **Expected:** `400`, `{"error":"This item is currently out of stock."}`

### 4.3 Get cart

- **GET** `{{baseUrl}}/api/cart`
- **Headers:** `Authorization: Bearer {{token}}`
- **Expected:** `200`, `data.subtotal`, `data.items`

### 4.4 Remove item

- **DELETE** `{{baseUrl}}/api/cart/items/{{cartItemId}}`
- **Expected:** `200`, updated cart in `data`

---

## 5. Orders

### 5.1 Checkout (place order)

- Re-add item if cart empty (4.1)
- **POST** `{{baseUrl}}/api/orders`
- **Headers:** `Authorization: Bearer {{token}}`, `Content-Type: application/json`
- **Body:**

```json
{
  "notes": "Postman test order"
}
```

- **Expected:** `201`, `data.status: PENDING`, `data.paymentStatus: UNPAID`
- **Save:** `orderId` = `data.id`

### 5.2 List orders

- **GET** `{{baseUrl}}/api/customer/orders`
- **Expected:** `200`, array includes your order

### 5.3 Empty cart checkout

- Clear cart, then **POST** `{{baseUrl}}/api/orders`
- **Expected:** `400`, cart empty message

---

## 6. Payments

### 6.1 Process payment

- **POST** `{{baseUrl}}/api/payments`
- **Headers:** `Authorization: Bearer {{token}}`, `Content-Type: application/json`
- **Body:**

```json
{
  "orderId": {{orderId}},
  "method": "gcash",
  "reference": "TEST-POSTMAN-001"
}
```

- **Expected:** `201`, `data.paymentStatus: PAID`, `data.orderStatus: IN_PROGRESS`

### 6.2 Payment status

- **GET** `{{baseUrl}}/api/customer/orders/{{orderId}}/payment`
- **Expected:** `200`, `paid: true`

---

## 7. Web sync check

1. Open admin: `{{baseUrl}}/login` → staff/admin login
2. Go to **Orders** — confirm Postman order appears
3. Edit product stock in **Products** — confirm `GET /api/products` shows new `stock`

---

## Checklist summary

| # | Test | Pass |
|---|------|------|
| 1 | GET /api/products | ☐ |
| 2 | POST /api/login (customer) | ☐ |
| 3 | GET /api/customer/profile | ☐ |
| 4 | POST /api/cart/items | ☐ |
| 5 | POST /api/orders | ☐ |
| 6 | POST /api/payments | ☐ |
| 7 | Staff cart → 403 | ☐ |
| 8 | Out of stock → 400 | ☐ |
| 9 | Order visible in admin | ☐ |

Run automated checks: `php bin/console app:api:verify-customer --base-url={{baseUrl}}`
