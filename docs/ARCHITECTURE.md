# MIM SFA Architecture

## Target

MIM SFA is a mobile-first Distributor Management / Sales Force Automation system for PT Mahameru Insan Mandiri.

## Architecture principles

1. Database first: business entities and constraints are explicit.
2. API first: transactions are exposed through authenticated server endpoints.
3. Authentication and permission-based RBAC are centralized.
4. Business rules are enforced server-side; UI validation is only a convenience layer.
5. Audit-sensitive mutations are logged.
6. Configuration is data-driven where practical.
7. MVP remains compatible with common shared hosting.
8. Production database is MySQL/PostgreSQL-ready.
9. External data sources such as Google Sheets are integration adapters, not business-logic dependencies.

## Technology direction

The initial production target uses a PHP/MySQL-compatible backend and a lightweight mobile-first web frontend because it is deployable on shared hosting. The application is structured so the database and integration layer can evolve without changing the core sales workflow.

## Modules

- Authentication & RBAC
- Company Settings
- Users, Roles, Permissions
- Areas & Sales Assignment
- Outlet Master
- Product Master
- Sales Route / Plan Call
- Attendance
- Warehouse Loading
- Sales Stock
- Visit / Check-in / Checkout
- EC/OC Order Workflow
- Returns
- Settlement
- KPI Dashboard
- Report Center
- Audit Log

## Visit invariants

A salesman may have only one active visit. Check-in requires valid GPS, outlet coordinates, radius <= configured limit (default 100m), mandatory outlet photo, operational hours, and no detectable mock location when blocking is enabled. The server stores the check-in timestamp and coordinates. Checkout is rejected until the configured minimum visit duration (default 5 minutes) has elapsed. Orders require an active visit and are rejected after checkout.

## Roles

The initial roles are OWNER, ADMIN, SUPERVISOR, SALES, and WAREHOUSE. Access is permission-based rather than hard-coded to role names.

## Data flow

Master data -> daily planning -> field execution -> order -> stock movement -> settlement -> KPI/reporting.
