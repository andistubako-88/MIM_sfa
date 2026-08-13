# Mahameru Transaction Scenarios

These scenarios are the acceptance checklist for the order-engine staging database.

| ID | Scenario | Expected result |
|---|---|---|
| T01 | Load 10 units from warehouse to a Sales location | Warehouse -10, Sales +10, one LOADING movement |
| T02 | Reserve 5 units from Sales stock of 10 | Reservation RESERVED 5; physical stock remains 10 |
| T03 | Another order reserves 6 from the same 10 while 5 is reserved | Rejected; available-to-reserve is 5 |
| T04 | Commit T02 | Sales stock becomes 5; SALE movement exists; reservation COMMITTED |
| T05 | Commit same order twice | Second attempt rejected; no second deduction |
| T06 | Create delivery for approved order | One active delivery with order items |
| T07 | Create delivery for same order twice | Second attempt rejected |
| T08 | Pay invoice partially | Invoice PARTIAL and paid_total increases atomically |
| T09 | Pay more than outstanding | Rejected and paid_total unchanged |
| T10 | Pay remaining balance | Invoice becomes PAID |
| T11 | Return 4 from delivery of 10 | Return POSTED; returnable becomes 6 |
| T12 | Return another 7 | Rejected; no partial write |
| T13 | Sales requests another Salesman's stock/report | Rejected with 403 |
| T14 | Non-Owner requests Owner-only finance/report field | Restricted by permission/role |
| T15 | Failure midway through a transaction | Entire transaction rolls back |

## Acceptance rule

A scenario is only considered passed when both the API response and the resulting database state are correct.
