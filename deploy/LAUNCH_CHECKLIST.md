# DogoFlow 3.0 production launch checklist

Use this as a manual release gate. A checked item means it was tested on the actual deployment, not merely reviewed in code.

## Hosting and configuration
- [ ] Keep `core/`, `database/`, and `storage/` outside `public_html/`; deploy only the contents of `public_html/` as the web root.
- [ ] Set `DOGOFLOW_ENV=production`, `DOGOFLOW_URL`, and the production DB host/name/user/password in the hosting environment or private configuration.
- [ ] Confirm the database name is `dogo` and the DB user has only the permissions the app needs.
- [ ] Enable HTTPS and redirect HTTP to HTTPS.
- [ ] Ensure PHP error display is off; logs are stored outside the public web root and are not web-accessible.
- [ ] Remove public diagnostic/test files not required for normal operation.
- [ ] Ensure product uploads are not executable and file type/size validation is enabled.
- [ ] Set correct ownership and minimum necessary write permissions for upload and private storage directories.

## Accounts and access
- [ ] Test customer, seller, affiliate, and admin registration/login/logout.
- [ ] Confirm each role cannot access another role's private pages by typing URLs directly.
- [ ] Change seeded/default admin credentials and use a unique strong password.
- [ ] Confirm suspended/rejected accounts cannot perform restricted actions.

## Marketplace and orders
- [ ] Test category selection on add/edit product forms with active categories in the database.
- [ ] Test image upload, replacement, deletion, and invalid file rejection.
- [ ] Test store approval and public visibility.
- [ ] Test cart stock validation and checkout with a Paystack test key.
- [ ] Confirm payment callback and webhook verify the transaction server-side and cannot fulfill an order twice.
- [ ] Test cancellation/refund and stock restoration behavior before enabling those flows for customers.

## Earnings, affiliates, and notifications
- [ ] Test seller earnings against known completed order items and confirm the business's commission/fee policy.
- [ ] Test withdrawal requests, admin approve/process/paid/rejected/failed transitions, manual payment recording, and duplicate/concurrent requests.
- [ ] Test affiliate referral attribution, seller approval, exactly-once GH₵10 reward creation, and payout reconciliation through Admin Withdrawals.
- [ ] Configure authenticated SMTP or another approved mail transport; verify queued, sent, and failed email behavior.
- [ ] Verify notification content does not expose passwords, tokens, or payment secrets.

## Backups and monitoring
- [ ] Take a database and file backup and test restoring them to a staging copy.
- [ ] Configure scheduled jobs only after verifying their command paths and execution user.
- [ ] Review PHP, application, payment, and web-server logs after test transactions.
- [ ] Keep a rollback copy of the previous release and document the release date/version.
- [ ] Run a full test purchase on staging before accepting live payments.

**Launch caution:** A passing system-check page is not a security certification. Do not enable live payments until payment, stock, commission, and payout paths have been tested end-to-end.
