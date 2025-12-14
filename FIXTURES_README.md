# Data Fixtures

This project includes comprehensive data fixtures for a designer shoes brand, including users, products, services, customers, and orders.

## Users Created

### Admin Account
- **Email:** rain@gmail.com
- **Username:** rain
- **Password:** rain123
- **Role:** ROLE_ADMIN
- **Status:** ACTIVE

### Staff Account 1
- **Email:** staff@gmail.com
- **Username:** bea
- **Password:** rain123
- **Role:** ROLE_STAFF
- **Status:** ACTIVE

### Staff Account 2
- **Email:** staff2@example.com
- **Username:** darl
- **Password:** rain123
- **Role:** ROLE_STAFF
- **Status:** ACTIVE

## Products Created

The fixtures include **15 designer shoes** across different categories:

- **Formal Shoes:** Luxury Leather Oxford, Executive Derby Shoes, Classic Monk Strap, Limited Edition Wingtips, Heritage Brogues, Luxury Evening Shoes
- **Casual Shoes:** Designer Sneaker Pro, Italian Loafers, Casual Canvas Sneakers, Designer Moccasins, Business Casual Slip-Ons, Minimalist Walking Shoes
- **Sports Shoes:** Sport Running Elite, Athletic Training Shoes
- **Outdoor Shoes:** Premium Boots Collection

Price range: $120 - $650
Stock levels: 8 - 60 units per product

## Services Created

The fixtures include **10 shoe-related services**:

1. **Custom Shoe Design** - $850.00 (72 hours, 3 revisions)
2. **Premium Shoe Repair** - $75.00 (24 hours, 1 revision)
3. **Shoe Resoling Service** - $120.00 (48 hours, 1 revision)
4. **Leather Conditioning & Care** - $45.00 (12 hours, 1 revision)
5. **Shoe Stretching Service** - $35.00 (24 hours, 1 revision)
6. **Color Matching & Dyeing** - $95.00 (36 hours, 2 revisions)
7. **Orthotic Insole Customization** - $150.00 (48 hours, 2 revisions)
8. **Express Shine & Polish** - $25.00 (2 hours, 0 revisions)
9. **Shoe Restoration Package** - $200.00 (96 hours, 2 revisions)
10. **Bespoke Shoe Consultation** - $150.00 (2 hours, 1 revision)

## Customers Created

The fixtures include **8 customer accounts** (ROLE_USER):

- john.doe@example.com (username: johndoe)
- sarah.smith@example.com (username: sarahsmith)
- michael.johnson@example.com (username: michaelj)
- emily.brown@example.com (username: emilyb)
- david.wilson@example.com (username: davidw)
- lisa.anderson@example.com (username: lisaa)
- robert.taylor@example.com (username: robertt)
- jennifer.martinez@example.com (username: jenniferm)

**Default Password for all customers:** customer123

## Orders Created

The fixtures include **10 customer orders** with various statuses:

- **Completed Orders:** 4 orders (ORD-2024-001, 004, 007, 009)
- **In Progress Orders:** 3 orders (ORD-2024-002, 005, 008)
- **Pending Orders:** 3 orders (ORD-2024-003, 006, 010)

Orders include various services and payment statuses (PAID, UNPAID, PARTIALLY_PAID).

## How to Load Fixtures

Run the following command to load all fixtures:

```bash
php bin/console doctrine:fixtures:load
```

**Warning:** This command will purge your database and reload all fixtures. Make sure you have a backup if needed.

To load fixtures without purging (append mode), use:

```bash
php bin/console doctrine:fixtures:load --append
```

## Fixture Loading Order

The fixtures are automatically loaded in the correct order due to dependencies:

1. **UserFixtures** - Creates admin and staff users
2. **ProductFixtures** - Creates designer shoes (depends on UserFixtures)
3. **ServiceFixtures** - Creates shoe services
4. **CustomerFixtures** - Creates customer accounts
5. **OrderFixtures** - Creates orders with order items (depends on CustomerFixtures and ServiceFixtures)

## Security Note

**IMPORTANT:** Change these default passwords immediately after loading fixtures in a production environment!

