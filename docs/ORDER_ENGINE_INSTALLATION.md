# Mahameru Order Engine — Installation & Validation

## Migration order

Run the database files in this order against the same `mim_sfa` database:

1. `database/schema.sql`
2. `database/seed_rbac.sql`
3. `database/visit_schema.sql`
4. `database/order_schema.sql`
5. `database/order_permissions.sql`
6. `database/inventory_schema.sql`
7. `database/inventory_seed_permissions.sql`
8. `database/order_stock_reservation.sql`
9. `database/delivery_return_schema.sql`
10. `database/finance_schema.sql`
11. `database/report_permissions.sql`

The order is intentional because later schemas reference users, roles, sales, outlets, products, visits and orders created earlier.

## Runtime prerequisites

- PHP 8.2+
- MySQL 8.0+ / compatible MariaDB version supporting the used foreign keys and transactions
- PDO MySQL enabled
- PHP sessions enabled
- HTTPS in production

## API transaction rules

### Order

`SUBMITTED` order can reserve stock. A reservation is locked and checked against other active reservations.

### Commit

Order commit locks the order, reservation and stock balance. It re-checks available stock before deducting it and records a `SALE` stock movement in the same database transaction.

### Delivery

Delivery requires an `APPROVED` order and prevents multiple active deliveries for one order.

### Payment

Payment locks the invoice, calculates remaining balance server-side and rejects payments above outstanding balance.

### Return

Return quantity is checked against delivered quantity minus previously posted returns for the same delivery/product.

### Settlement

Cash settlement calculates expected cash from posted CASH payments for the selected salesman/date. The submitted amount is never treated as the source of truth for expected cash.

## Pre-production checklist

- [ ] Import migrations in the order above
- [ ] Verify all foreign keys are created successfully
- [ ] Run GitHub Actions PHP lint
- [ ] Create test users for OWNER, ADMIN, SUPERVISOR, WAREHOUSE and SALES
- [ ] Create one warehouse and one sales stock location
- [ ] Load test stock
- [ ] Create test visit and order
- [ ] Verify reservation conflict with two concurrent orders
- [ ] Verify stock cannot become negative
- [ ] Verify delivery cannot be duplicated
- [ ] Verify payment cannot exceed invoice outstanding
- [ ] Verify return cannot exceed delivered-minus-returned quantity
- [ ] Verify Sales cannot access another Salesman's stock/order/payment
- [ ] Verify non-Owner cannot access Owner-only report fields
- [ ] Verify rollback by intentionally failing a transaction in staging

## Production rule

Do not merge to `main` or deploy to production until the checklist is completed against a real staging database. GitHub PHP lint validates syntax only; it does not prove database connectivity or business-rule correctness.
