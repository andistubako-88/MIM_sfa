# MIM SFA — Mahameru Insan Mandiri

Sales Force Automation and Distributor Management System for PT Mahameru Insan Mandiri.

## Foundation

The project is being built incrementally from the existing repository.

Implementation principles:
- Database → API → Authentication/Authorization → Business Logic → UI
- Shared-hosting compatible MVP
- MySQL/PostgreSQL-ready production architecture
- Google Sheets may be used as an initial integration/data source
- Permission-based RBAC and audit trails
- Mobile-first sales workflow

## Core workflow

Login → Attendance → Loading → Sales Stock → Plan Call → Check In → Visit → Order/EC/OC → Check Out → Return → Settlement → KPI/Reports.

## Visit rules

- One active visit per salesman
- Check-in radius <= 100 meters from outlet coordinates
- Outlet photo required
- Valid GPS required
- Fake/mock GPS should block check-in when detectable
- Operational hours enforced
- Minimum visit duration: 5 minutes
- Order allowed only after check-in and before checkout
