# Mahameru Test Harness

Run locally in an isolated test checkout:

```bash
php -l api/auth.php
php tests/smoke_test.php
```

Database invariants require a dedicated MySQL test database and the schema installation sequence in `docs/ORDER_ENGINE_INSTALLATION.md`.

Do not run test fixtures against production.
