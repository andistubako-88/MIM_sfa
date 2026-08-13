# Mahameru Testing Strategy

## Layers

1. PHP syntax lint for every API/test PHP file.
2. Repository smoke test for required modules.
3. Database invariant checks against an isolated MySQL test database.
4. Transaction scenarios: reservation conflict, stock underflow, duplicate delivery, overpayment, excessive return.
5. RBAC scenarios: Sales data isolation and Owner-only reports.

## Required staging sequence

1. Create an isolated TEST database.
2. Apply schema files in `docs/ORDER_ENGINE_INSTALLATION.md` order.
3. Seed roles, permissions and deterministic test data.
4. Run `tests/database_integration.sql`.
5. Execute API scenario tests with CSRF/authentication enabled.
6. Require all GitHub Actions checks to pass before merge.

## Production rule

Never execute test fixtures against production. Never disable foreign keys, authentication, CSRF, or transaction handling to make a test pass.
