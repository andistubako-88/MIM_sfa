# Sales Workspace Regression Contract

This contract validates the post-hardening sales workflow against the existing server-side APIs.

## Required flow

1. Sales user authenticates.
2. Sales Workspace obtains `GET api/visit.php?action=active`.
3. Order UI MUST remain disabled when no Visit is `ACTIVE`.
4. After successful Check-In, `api/visit.php?action=active` MUST return the active visit.
5. Order creation MUST use `api/orders.php` and include the active `visit_id`.
6. Backend MUST reject order creation when the Visit is not `ACTIVE`.
7. Check-Out MUST be allowed only for the active visit owned by the authenticated salesman.
8. After Check-Out, the active visit endpoint MUST no longer return that visit.

## Security invariants

- `orders.create` permission remains server-side enforced.
- Visit ownership remains server-side enforced.
- CSRF protection remains enabled for state-changing requests.
- Check-In photo remains mandatory.
- Radius, operational-hours, fake-GPS and minimum-duration rules remain backend controlled.

## Regression intent

This document is intentionally kept as a repository contract so future Sales Workspace changes cannot silently bypass the Visit Engine.
