# DogoFlow 3.0 Build Roadmap

## Completed in this package

### Phase 1 — Foundation
- Modular PHP structure
- PDO database layer
- secure sessions
- CSRF and output escaping
- role authorization
- core marketplace schema
- audit, notification, wallet and withdrawal tables

### Phase 2 — Authentication
- customer registration
- password hashing
- email verification token flow
- login
- logout
- password reset token flow
- role-based redirect

### Phase 3 — Marketplace foundation
- public product browsing
- product details
- seller product list
- seller create/edit product
- unique product slugs

### Phase 4 — Payment foundation
- Paystack verification service
- signed webhook verification
- amount validation
- idempotent payment handling
- order state transition to paid

### Phase 7 — Affiliate foundation
- affiliate profile
- click/commission/withdrawal data access
- referral code foundation

### Phase 8 — Admin foundation
- admin authorization
- dashboard metrics
- user status management
- product/order/payment views
- audit log viewer

## Remaining production work

### Phase 3 completion
- secure image upload
- categories
- product search/filter
- stock reservation
- seller approval

### Phase 4 completion
- checkout order creation
- Paystack initialization
- callback verification
- refunds
- duplicate event protection across all financial actions
- payment reconciliation

### Phase 5
- customer dashboard
- order history/details
- profile
- wallet views
- notification center

### Phase 6
- seller dashboard
- seller approval workflow
- inventory
- seller order management
- earnings ledger

### Phase 7 completion
- referral URLs
- click attribution
- conversion attribution
- commission rules
- pending -> available -> paid lifecycle
- withdrawal eligibility

### Phase 8 completion
- seller moderation
- withdrawal approval
- payment reconciliation
- SMS administration
- system settings
- full audit events

### Phase 9 — Production hardening
- rate limiting
- login brute-force protection
- secure headers
- upload isolation
- environment/secret management
- database backups
- error logging
- queue/cron notification processing
- performance/index review
- end-to-end payment tests
- security regression tests
- deployment checklist

## Phase 12 — Seller Operations & Earnings
- Seller earnings are calculated from completed seller order items linked to successful payments.
- Sellers can request payouts to mobile money or bank destinations.
- Requests are reserved against available earnings while pending, approved, processing, or paid.
- Admins can approve, reject, mark processing, and mark paid only after entering an external payment reference.
- No payout is sent automatically; administrators must verify that payment was actually made.
- Existing installations: import `database/migration_seller_earnings.sql`.


## Phase 13 — Affiliate onboarding rewards
- Referral-code attribution during seller registration
- GH₵10 reward after admin approval, with duplicate prevention
- Affiliate overview, referral link, and reward history
- Additive migration only; no replacement schema
