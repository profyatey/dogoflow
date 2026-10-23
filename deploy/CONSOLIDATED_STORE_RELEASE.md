# DogoFlow 3.0 — Consolidated Store Release

## Scope

This incremental release is based on `AfterError_Dogoflow.zip`. The baseline already contained the previous seller auto-approval, CSRF/session, product gallery/plan-limit, and upload-progress work. This release adds:

- Permanent seller-code assignment during new seller registration and a backfill for existing blank codes.
- Optional manual affiliate referral codes on seller registration, with active/verified affiliate validation and self-referral protection. Existing `?ref=` referral-link attribution remains supported.
- Readable storefront URLs (`/store/<slug>`), canonical metadata, redirects from old `store.php?slug=` links, and stable assigned slugs (a store name change does not silently change its slug).
- WhatsApp Order and Call to Order on product pages, using the seller business phone first and account phone as fallback. WhatsApp messages include product URL and selected quantity when present.
- Store QR code generation/download and WhatsApp store-sharing actions in seller marketing tools.
- Privacy-conscious store/product view tracking, deduplicated to one event per browser session per page entity per day, plus 30-day seller marketing summaries.

## Deploy

1. Back up the database and all files included in this ZIP.
2. In phpMyAdmin, select the DogoFlow database and run `database/migration_consolidated_store_release.sql`. It assigns codes only to profiles whose code is blank and creates `marketplace_view_events` if missing.
3. Extract the ZIP at the project root, preserving the included `public_html/`, `core/`, and `database/` paths. If you maintain custom rewrite rules on the host, merge `public_html/.htaccess` carefully.
4. Keep `public_html/uploads/products/.htaccess` in place to prevent script execution from uploaded files.
5. Test in staging before production: new seller registration and seller code; valid/invalid/manual affiliate codes; email verification and approval; pretty store URL and legacy redirect; editing store name while keeping the same URL; missing/valid phone on product detail; WhatsApp quantity text; QR render/download; store/product visit summaries.

## Notes and limitations

- No `.env`, credentials, Paystack keys, or SMTP secrets are included.
- Store slugs are permanent after first assignment to avoid breaking previously shared links. Changing the display name leaves the URL unchanged.
- QR generation loads the pinned QRCode.js build from cdnjs in the seller's browser. If that CDN is inaccessible, store sharing/copying still works but QR rendering/download will be unavailable until the script can load.
- WhatsApp links open a message for the customer to review and send. They do not create a paid DogoFlow order, change order status, or bypass Paystack checkout.
- View tracking does not store IP addresses or user-agent strings and does not change affiliate attribution. Counts are session/day page views rather than a fraud-resistant analytics measure.
- PHP/static tests cannot verify live DB constraints, Apache rewrite configuration, actual browser QR generation, SMTP delivery, or Paystack gateway behavior. These require testing in your AvesHost staging environment.

## Rollback

Restore the backed-up PHP and `.htaccess` files. The analytics table can remain unused after rollback. If removing it, export data first and then drop `marketplace_view_events`. Seller codes assigned to real profiles are permanent identifiers and should not be cleared.
