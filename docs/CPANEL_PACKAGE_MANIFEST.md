# PT MIM SFA — cPanel Production Package Manifest

## Package objective

The cPanel package must contain only production runtime files. Do not upload the complete Git repository as the web root.

## Include

```text
api/
config/config.production.example.php
public/
database/                         # only when migration is being performed manually
README.md                           # optional
```

For the web server Document Root, expose only:

```text
public/
```

## Exclude from web root

```text
.github/
docs/
tests/
.git/
.gitignore
```

Production `config/config.php` is created manually on the server from the example template and must never be committed.

## Recommended server layout

```text
/home/CPANEL_USER/mim_sfa/
├── api/
├── config/
│   └── config.php
├── database/
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

Document Root:

```text
/home/CPANEL_USER/mim_sfa/public
```

## Database package rule

Do not automatically execute SQL during application upload.

Perform database migration separately, after backup and after confirming the production database target.

Migration order:

1. `database/schema.sql`
2. dependent schemas
3. `database/finance_schema.sql`
4. `database/migrations/20260815_finance_hardening.sql`
5. required role/permission seed

## Production configuration

Create `config/config.php` on the server. Set:

- `env = production`
- `timezone = Asia/Jakarta`
- real HTTPS `base_url`
- production MySQL host/database/user/password
- `utf8mb4`
- CSRF enabled

Never copy `CHANGE_ME` credentials into production.

## Final verification

Before declaring deployment complete:

```text
HTTP smoke test        PASS
API routing             PASS
Authentication          PASS
RBAC                    PASS
Upload security         PASS
Database connectivity   PASS
Migration verification  PASS
Business smoke test     PASS
```
