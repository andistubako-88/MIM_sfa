# MIM SFA — cPanel Deployment Architecture

## Recommended layout

Use a dedicated application directory outside the web root when the hosting plan supports a custom document root:

```text
/home/CPANEL_USER/mim_sfa/
├── api/
├── config/
├── database/
├── docs/
├── tests/
└── public/
    ├── .htaccess
    ├── api/
    │   └── index.php      # cPanel API adapter
    ├── index.php
    ├── login.php
    ├── dashboard.php
    ├── sales.php
    ├── order.php
    ├── visit.php
    ├── assets/
    └── uploads/
```

Set the domain/subdomain document root to:

```text
/home/CPANEL_USER/mim_sfa/public
```

Do **not** upload production credentials to GitHub.

## API routing on shared hosting

The source API implementation remains in the private `api/` directory. Because normal shared cPanel hosting cannot rely on a server-level Apache `Alias`, the production package includes a small public adapter at `public/api/index.php`.

`public/.htaccess` maps:

```text
/api/health.php
/api/login.php
/api/order.php
...
```

to:

```text
/api/index.php?endpoint=health.php
/api/index.php?endpoint=login.php
/api/index.php?endpoint=order.php
...
```

The adapter then loads the matching script from the private `/api` directory. This keeps the application/config/database/tests directories outside the document root while remaining compatible with ordinary cPanel/Apache hosting.

**Important:** do not remove `public/api/index.php` or the API rewrite rules from `public/.htaccess`.

Do not expose `config/`, `database/`, `docs/`, or `tests/` as web directories.

## Upload protection

Keep `public/uploads/.htaccess` in place. It disables directory listing and blocks PHP-family extensions from execution.

## Production configuration

Create the real production configuration on the server from `config/config.example.php`. Replace:

- `base_url` → `https://sfa.mahameruinsanmandiri.id`
- database host
- database name
- database username
- database password
- production session/security settings as required by the hosting environment

Never commit the real production config.

## PHP

Use PHP 8.3 or newer. The application and CI are validated on PHP 8.3.

## Database migration order

Run migrations in this order on a backup/staging database first:

1. Core schema
2. Dependent application schemas
3. Finance schema
4. `database/migrations/20260815_finance_hardening.sql`
5. Required seed/permission data, including `database/finance_approval_permissions.sql`

Verify the resulting tables, permissions, and indexes before production cutover.

## Production cutover checklist

- [ ] Backup production database
- [ ] Upload application package to `/home/CPANEL_USER/mim_sfa/`
- [ ] Set document root to `/home/CPANEL_USER/mim_sfa/public`
- [ ] Create production `config/config.php` outside Git
- [ ] Set PHP to 8.3+
- [ ] Confirm `public/api/index.php` exists
- [ ] Confirm `public/.htaccess` is enabled
- [ ] Confirm HTTPS
- [ ] Confirm `public/uploads` is writable but PHP execution is blocked
- [ ] Run database migrations
- [ ] Verify `/api/health.php`
- [ ] Verify login
- [ ] Verify RBAC
- [ ] Verify Sales Check-In/Check-Out
- [ ] Verify EC/OC order
- [ ] Verify Loading/Stock
- [ ] Verify Delivery/Return
- [ ] Verify Invoice/Payment
- [ ] Verify Settlement
- [ ] Verify Owner-only Report Center
- [ ] Review PHP/Apache error logs

## Current CI status

The finance permission fix was merged to `main` after the final Quality Gate passed all of these checks:

- PHP syntax check
- Sales Workspace regression guard
- Finance hardening regression guard
- Visit and Finance hardening contract guard
- Smoke test
- E2E contract test
- MySQL integration preflight
- Real API transaction E2E

Production deployment should still be validated against the final `main` commit before cutover.
