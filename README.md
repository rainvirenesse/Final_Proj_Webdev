# RAIN — Web Admin + Customer API + Mobile Integration

Symfony 7 application with an admin/staff dashboard, a **Customer REST API** for mobile apps, JWT authentication, and role-based access control.

## Features

| Area | Description |
|------|-------------|
| **Web admin** | Products, stock, orders, services, users (`/admin`, `/staff`) |
| **Customer API** | Products, cart, orders, payments, profile (`/api/*`) |
| **Auth** | JWT (`POST /api/login`), bcrypt/argon2 passwords, email verification |
| **RBAC** | `ROLE_CUSTOMER`, `ROLE_STAFF`, `ROLE_ADMIN` |
| **Mobile** | React Native API clients in `app-integration/react-native/` |

## Requirements

- PHP 8.2+
- Composer
- MySQL or MariaDB
- Node.js (optional, for asset build)
- OpenSSL (JWT keys)

## Installation

### 1. Clone and install dependencies

```bash
composer install
```

### 2. Environment

Copy **`.env.dist`** to `.env.local` in the project root (see all variables there), or use:

```env
APP_ENV=dev
APP_SECRET=change-me-to-a-random-string

DATABASE_URL="mysql://user:password@127.0.0.1:3306/rain_db?serverVersion=8.0"

# Used for product imageUrl in API responses
APP_URL=http://127.0.0.1:8000
DEFAULT_URI=http://127.0.0.1:8000

JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=change-me

# Allow mobile app origin (adjust for your LAN IP)
CORS_ALLOW_ORIGIN='^https?://(localhost|127\.0\.0\.1|192\.168\.\d+\.\d+)(:[0-9]+)?$'
```

Generate JWT keys (if not already present):

```bash
php bin/console lexik:jwt:generate-keypair
```

### 3. Database

```bash
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console doctrine:fixtures:load --no-interaction
```

Optional — assign `ROLE_CUSTOMER` to existing users:

```bash
php bin/console app:users:assign-customer-role
```

### 4. Run the server

**Local only:**

```bash
symfony server:start
# or: php -S 127.0.0.1:8000 -t public
```

**Accessible from phone/emulator on your LAN:**

```bash
symfony server:start --host=0.0.0.0 --port=8000
```

Use your PC’s IP (e.g. `http://192.168.1.20:8000`) in Postman and the mobile app.

## Deploy to Railway (production)

Production deployment for the Customer API + JWT + MySQL is documented in **[docs/RAILWAY.md](docs/RAILWAY.md)**.

Quick summary:

1. Create a Railway project with **MySQL** + this repo as a PHP service.
2. Set variables from **`.env.dist`** (`APP_SECRET`, `DATABASE_URL`, `APP_URL`, `JWT_PASSPHRASE`, etc.).
3. Railway runs `bin/railway-build.sh` (build) and `bin/railway-start.sh` (migrations + server).
4. Point the mobile app at `EXPO_PUBLIC_API_URL=https://your-app.up.railway.app`.

### 5. Default accounts (after fixtures)

| Role | Email | Password | Notes |
|------|-------|----------|-------|
| Admin | (see `UserFixtures`) | — | Web dashboard |
| Staff | (see `UserFixtures`) | — | Web dashboard |
| Customer | `john.doe@example.com` | `customer123` | **Use for Customer API / mobile** |

Customers must be **email-verified** for API login (fixtures set `isVerified`).

## Customer API (quick test)

```bash
# Public catalog
curl http://127.0.0.1:8000/api/products

# Login (customer)
curl -X POST http://127.0.0.1:8000/api/login \
  -H "Content-Type: application/json" \
  -d "{\"email\":\"john.doe@example.com\",\"password\":\"customer123\"}"

# Profile (replace TOKEN)
curl http://127.0.0.1:8000/api/customer/profile \
  -H "Authorization: Bearer TOKEN"
```

Full reference: **[docs/API_CUSTOMER.md](docs/API_CUSTOMER.md)**  
Postman checklist: **[docs/POSTMAN_CHECKLIST.md](docs/POSTMAN_CHECKLIST.md)**  
Demo script: **[docs/DEMO_SCRIPT.md](docs/DEMO_SCRIPT.md)**

## Verify API automatically

With the server running:

```bash
php bin/console app:api:verify-customer --base-url=http://127.0.0.1:8000
```

On Windows PowerShell:

```powershell
.\scripts\verify-customer-api.ps1 -BaseUrl "http://192.168.1.20:8000"
```

## React Native mobile app

Copy `app-integration/react-native/src/` into your Expo project. See **[app-integration/react-native/README.md](app-integration/react-native/README.md)**.

Set in mobile `.env`:

```env
EXPO_PUBLIC_API_URL=http://192.168.1.20:8000
```

## Web dashboard

| URL | Role |
|-----|------|
| `/login` | Login |
| `/admin/dashboard` | Staff / Admin |
| `/admin/products` | Product management |
| `/admin/orders` | Order management |
| `/admin/stock` | Stock ledger |

## Project structure

```
src/
  Controller/Api/          # Customer REST (Catalog, Cart, Orders, Payments)
  Controller/Api/Customer/ # Customer API (profile, cart, orders)
  Entity/                  # Doctrine entities
  Service/Api/             # Cart, orders, payments, product serializer
  Security/                # JWT, voters, user checker
docs/
  API_CUSTOMER.md          # API reference
  POSTMAN_CHECKLIST.md     # Manual API test steps
  DEMO_SCRIPT.md           # Presentation walkthrough
app-integration/react-native/  # Mobile API client samples
```

## Rubric alignment (self-check)

| Criterion | Evidence in project |
|-----------|---------------------|
| Customer API (5+ endpoints) | `docs/API_CUSTOMER.md`, `debug:router` |
| JWT + passwords | Lexik JWT, `security.yaml` hashers |
| RBAC | `ROLE_*`, `CustomerApiVoter`, `access_control` |
| DB design | Entities + migrations |
| Mobile ↔ web sync | Same database; admin changes visible in API |
| Error handling | `ApiResponse`, 400/401/403/404, stock errors |

## Troubleshooting

| Issue | Fix |
|-------|-----|
| 403 on `/api/customer/profile` | Log in as **customer**, not staff/admin |
| 401 on cart | Send `Authorization: Bearer <token>` from `POST /api/login` |
| Product not found | Use `id` from `GET /api/products` (not always `1`) |
| Session expired on mobile | Fix JWT provider (email); re-login; see `app-integration/react-native/README.md` |
| Phone cannot reach API | `symfony server:start --host=0.0.0.0`, use LAN IP, same Wi‑Fi |

## License

Proprietary — course / project use.
