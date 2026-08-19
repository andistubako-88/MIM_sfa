# PT MIM SFA — cPanel Pre-Deploy Checklist

## Deployment layout

Recommended:

```text
/home/CPANEL_USER/mim_sfa/
├── api/
├── config/
├── database/
├── docs/
├── tests/
└── public/
    ├── index.php
    ├── login.php
    ├── dashboard.php
    ├── sales.php
    ├── order.php
    ├── visit.php
    ├── assets/
    ├── uploads/
    └── api/
```

Set the domain/subdomain **Document Root** to:

```text
/home/CPANEL_USER/mim_sfa/public
```

Do not upload the whole repository directly into `public_html`.

## Before upload

- [ ] Confirm PHP 8.3 is available.
- [ ] Confirm MySQL 8.x or compatible MySQL/MariaDB version supported by the schema.
- [ ] Enable `pdo_mysql`.
- [ ] Enable Apache rewrite (`mod_rewrite`).
- [ ] Confirm the domain can use `public/` as Document Root.
- [ ] Prepare a production database backup/restore point.
- [ ] Create production `config/config.php` from `config/config.example.php`.
- [ ] Never commit production credentials.
- [ ] Set a strong production database password.
- [ ] Set production `base_url` to the real HTTPS URL.
- [ ] Enable HTTPS before production use.

## Database migration order

1. `database/schema.sql`
2. `database/visit_schema.sql`
3. `database/attendance_plan_schema.sql`
4. `database/order_schema.sql`
5. `database/inventory_schema.sql`
6. `database/order_stock_reservation.sql`
7. `database/delivery_return_schema.sql`
8. `database/finance_schema.sql`
9. `database/migrations/20260815_finance_hardening.sql`
10. RBAC/permission seed SQL required by the selected build.

Always take a production backup before step 1 when an existing database is present.

## File permissions

Recommended baseline:

```text
PHP files: 0644
Directories: 0755
config/config.php: 0640 or 0600 when supported
```

The web server must be able to read application files but upload directories must not allow PHP execution.

## Upload security

Verify:

- [ ] `public/uploads/.htaccess` exists.
- [ ] PHP, PHTML and PHAR execution is blocked in uploads.
- [ ] Directory listing is disabled.
- [ ] Application validates uploaded file type/size.
- [ ] Upload directory is writable by PHP but not executable as PHP.

## HTTP smoke test

After DNS/HTTPS is active:

```bash
bash tests/cpanel_smoke.sh https://your-domain.example
```

Expected:

```text
GET /                         -> 200/302
GET /api/<valid-endpoint>.php -> endpoint response
GET /api/not-found.php        -> 404
GET /config/                  -> 403
GET /database/                -> 403
GET /tests/                   -> 403
Traversal attempt             -> 404
```

## Application smoke test

- [ ] Login as OWNER.
- [ ] Login as SALES.
- [ ] Login as WAREHOUSE.
- [ ] Login as FINANCE.
- [ ] Verify role boundaries.
- [ ] Check-in within 100m of outlet.
- [ ] Check-in photo is mandatory.
- [ ] Check-out below 5 minutes is rejected.
- [ ] Check-out after minimum duration completes visit.
- [ ] Create EC/OC order only during valid visit.
- [ ] Loading reserves/commits stock correctly.
- [ ] Delivery reconciles with order.
- [ ] Return goes to warehouse.
- [ ] Invoice cannot be created from mismatched delivery.
- [ ] Payment rejects overpayment.
- [ ] Payment idempotency replay does not duplicate payment.
- [ ] Settlement only counts POSTED CASH payments.
- [ ] Owner-only Report Center is enforced.

## Production safety rules

Do not:

- [ ] run test scripts against production unless explicitly designed as read-only smoke tests;
- [ ] import test seed data into production;
- [ ] expose `database/`, `tests/`, or `docs/` publicly;
- [ ] place production passwords/API secrets in Git;
- [ ] deploy while the database migration backup is unavailable.

## Rollback

If deployment fails:

1. Disable public access or maintenance mode.
2. Restore the previous application package.
3. Restore the database only if a migration has changed schema/data and rollback is required.
4. Verify login and database connectivity.
5. Re-run smoke tests.

Production deployment is considered complete only after the HTTP smoke test and application smoke test pass.
