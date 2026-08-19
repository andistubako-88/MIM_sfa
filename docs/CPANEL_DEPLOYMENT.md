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

## Important API routing constraint

The current source keeps `api/` outside `public/`. A normal Apache document root of `public/` cannot directly expose `/api/*.php` without an explicit server-level routing rule. Before production deployment, choose one of these supported approaches:

1. Configure Apache/cPanel `Alias` or an equivalent server-level mapping from `/api` to `/home/CPANEL_USER/mim_sfa/api`.
2. Move/copy the API entrypoints into `public/api/` and update their relative includes so they continue to load the private application/config layer safely.

Do not expose `config/`, `database/`, `docs/`, or `tests/` as web directories.

## Upload protection

Keep `public/uploads/.htaccess` in place. It disables directory listing and blocks PHP-family extensions from execution.

## Production configuration

Create the real production configuration on the server from `config/config.example.php`. Replace:

- `base_url`
- database host
- database name
- database username
- database password
- production session/security settings as required by the hosting environment

Never commit the real production config.

## Database migration order

Run migrations in this order on a backup/staging database first:

1. Core schema
2. Dependent application schemas
3. Finance schema
4. `database/migrations/20260815_finance_hardening.sql`
5. Required seed/permission data

Verify the resulting tables/indexes before production cutover.

## Production cutover checklist

- [ ] Backup production database
- [ ] Create production config outside Git
- [ ] Set PHP to 8.3+
- [ ] Set document root to `public/` if using private application layout
- [ ] Configure `/api` routing explicitly
- [ ] Confirm HTTPS
- [ ] Confirm uploads are writable but PHP execution is blocked
- [ ] Run database migrations
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

## Do not deploy yet

The GitHub Actions Quality Gate must be executed successfully against the final commit before production cutover. The current repository connector has no workflow-dispatch operation, so manual execution from GitHub Actions may still be required.
