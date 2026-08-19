# PT MIM SFA — Production Database Migration Manifest

## Purpose

This document is the controlled migration order for a fresh or existing production database. It is a procedure document, not an automatic web migration runner.

## Mandatory preconditions

- [ ] Application deployment package is backed up.
- [ ] Production database has a verified backup.
- [ ] Database name/host/user are confirmed.
- [ ] PHP application is configured for production but application traffic is not yet enabled.
- [ ] Migration is performed by an authorized operator.

## Migration order

Run the SQL files in this order. Stop immediately if any statement fails.

| Step | File | Purpose |
|---|---|---|
| 01 | `database/schema.sql` | Core users, roles, areas, sales, outlets, products and settings |
| 02 | `database/visit_schema.sql` | Visit/check-in/check-out data |
| 03 | `database/attendance_plan_schema.sql` | Attendance/visit planning |
| 04 | `database/order_schema.sql` | Order, order detail and order workflow |
| 05 | `database/inventory_schema.sql` | Warehouse/inventory structures |
| 06 | `database/order_stock_reservation.sql` | Stock reservation constraints/structures |
| 07 | `database/delivery_return_schema.sql` | Delivery and return structures |
| 08 | `database/finance_schema.sql` | Invoice, payment and settlement structures |
| 09 | `database/migrations/20260815_finance_hardening.sql` | Payment idempotency and settlement lookup index |
| 10 | Required RBAC/permission seed SQL | Production role permissions |

## Important dependency rules

`finance_schema.sql` depends on core and delivery tables, including `delivery_documents`, `outlets`, `sales`, and `users`. Do not run Finance before those schemas exist.

`20260815_finance_hardening.sql` must run only after `payments` exists because it alters the `payments` table.

## Fresh database verification

After migration, verify at minimum:

```sql
SELECT DATABASE();
SHOW TABLES;

SHOW COLUMNS FROM payments LIKE 'idempotency_key';
SHOW INDEX FROM payments WHERE Key_name = 'uq_payment_idempotency_key';
SHOW INDEX FROM payments WHERE Key_name = 'idx_payment_settlement_lookup';
SHOW INDEX FROM settlement_documents WHERE Key_name = 'uq_settlement_sales_date';
```

Verify the following tables exist:

```text
users
roles
permissions
role_permissions
areas
sales
sales_area
outlets
sales_outlet
products
outlet_products
visits
visit_activities
orders
order_items
inventory_movements
delivery_documents
invoices
payments
settlement_documents
```

## Existing production database

For an existing production database:

1. Do not rerun base `CREATE TABLE` files blindly.
2. Take a full database backup.
3. Compare the current schema against the repository migration state.
4. Apply only missing migrations.
5. Apply `20260815_finance_hardening.sql` only if its column/indexes do not already exist.
6. Verify foreign keys and indexes after changes.
7. Run application smoke tests before opening traffic.

## Finance hardening verification

Expected payment structure:

```text
payments.idempotency_key VARCHAR(100) NULL
UNIQUE uq_payment_idempotency_key(idempotency_key)
INDEX idx_payment_settlement_lookup(
  sales_id,
  payment_date,
  status,
  payment_method
)
```

Expected settlement uniqueness:

```text
UNIQUE uq_settlement_sales_date(sales_id, settlement_date)
```

## Rollback strategy

Base schema creation is not treated as a reversible application transaction. Rollback for production schema changes must use the verified database backup/snapshot when necessary.

For the Finance Hardening migration, if rollback is explicitly required and the schema state is known, remove only the added index/column after confirming no application traffic depends on them. Prefer restoring from a tested backup for destructive recovery.

## Final acceptance gate

Migration is accepted only when:

- [ ] All required tables exist.
- [ ] Foreign keys resolve successfully.
- [ ] Finance tables exist.
- [ ] `payments.idempotency_key` exists.
- [ ] Payment idempotency unique index exists.
- [ ] Settlement lookup index exists.
- [ ] Settlement sales/date uniqueness exists.
- [ ] Production configuration can connect to the database.
- [ ] HTTP cPanel smoke test passes.
- [ ] Login/RBAC smoke test passes.
- [ ] Finance/payment/settlement smoke test passes.

**Never execute this migration automatically from a public HTTP endpoint.**
