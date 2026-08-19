# MIM SFA Deep Audit — 2026-08-19

## Scope
Audit baseline: `main` as of 2026-08-19. This remediation branch contains only safe, additive fixes discovered during the initial audit.

## Confirmed strengths
- Role/permission based authentication and CSRF protection are implemented.
- Visit check-in validates operational hours, assigned outlet, GPS radius, mandatory photo, mock-location flag, and single active visit.
- Checkout enforces the configured minimum visit duration.
- Orders use status transitions and approval history.
- Inventory, delivery/return, finance, attendance, and reporting schemas are separated into dedicated SQL modules.
- Audit logging exists in the core schema.

## Remediations applied
1. Added idempotent `orders.view` permission seed because `api/order.php` requires it.
2. Added idempotent `orders.approve` permission seed because the approval endpoint requires it.
3. Granted `orders.view` to OWNER, ADMIN, SUPERVISOR, and SALES. Sales ownership filtering remains enforced by the API.
4. Added indexes supporting active-visit and order lookup paths.

## Remaining high-priority verification
- Verify every permission referenced by PHP endpoints exists in database seed/migrations.
- Verify every role has the intended permission set.
- Verify order creation explicitly requires an ACTIVE visit belonging to the authenticated salesman and rejects checkout-completed visits.
- Verify stock reservation/commit is atomic and cannot produce negative available stock under concurrent requests.
- Verify report center is owner-only at both UI and API layers.
- Strengthen anti-fake-GPS with server-side anomaly checks; client-provided mock-location flags are only one signal.
- Run PHP lint, database migration validation, API smoke tests, and end-to-end workflow tests before production deployment.
