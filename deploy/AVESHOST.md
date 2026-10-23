# DogoFlow 3.0 — AvesHost production deployment

The release has a private/core + public_html layout. Do **not** place `core/`, `database/`, `.env`, or `storage/` inside the public web root.

## 1. Upload the private application files

Place these directories one level above AvesHost `public_html/` (or another private directory supported by your host):

- `core/`
- `database/`
- `storage/`

`storage/logs` and `storage/cache` must be writable by PHP.

## 2. Upload the web root

Upload the **contents of this project's `public_html/` directory** into the hosting `public_html/` directory. Do not create `public_html/public_html/`.

The release already contains the required local CSS asset at `public_html/assets/css/dogoflow.css`.

## 3. Configure production settings

Set these outside the public web root when your host supports environment variables/private config:

```text
DOGOFLOW_ENV=production
DOGOFLOW_URL=https://YOUR-DOMAIN/
DOGOFLOW_TIMEZONE=Africa/Accra
DOGOFLOW_DB_HOST=...
DOGOFLOW_DB_NAME=...
DOGOFLOW_DB_USER=...
DOGOFLOW_DB_PASS=...
DOGOFLOW_PAYSTACK_SECRET=...
DOGOFLOW_MAIL_HOST=...
DOGOFLOW_MAIL_PORT=587
DOGOFLOW_MAIL_USERNAME=...
DOGOFLOW_MAIL_PASSWORD=...
DOGOFLOW_MAIL_ENCRYPTION=tls
DOGOFLOW_MAIL_FROM=...
DOGOFLOW_MAIL_FROM_NAME=DogoFlow
```

Production database credentials are intentionally **not embedded in the PHP source**.

## 4. Database

For a fresh database, import `database/schema.sql`. For an existing DogoFlow database, apply the phase migrations as needed and then apply:

`database/migration_production_hardening.sql`

That hardening migration creates/repairs the affiliate referral/reward and seller subscription structures, adds withdrawal notes, and seeds standard marketplace categories.

## 5. Paystack

Use Paystack test keys for staging. Confirm checkout, callback, webhook signature verification, amount verification and duplicate-event handling before switching to live keys.

Webhook endpoint:

`/api/webhooks/paystack.php`

Callback endpoint:

`/payment/callback.php`

## 6. Final smoke test

1. Open the homepage.
2. Register a customer and verify email.
3. Register a seller, verify email and confirm the seller is blocked until Admin approval.
4. Admin approves the seller.
5. Seller creates store/categories/products and uploads images.
6. Confirm the approved store and product are publicly visible.
7. Test cart → checkout → Paystack test payment.
8. Confirm duplicate callback/webhook does not duplicate fulfillment.
9. Test affiliate referral → seller registration → verification → approval → GH₵10 reward.
10. Test affiliate withdrawal → Admin approval → processing → paid.
11. Test Admin/Seller/Customer direct-URL authorization.

## Important

Do not upload the whole repository into `public_html`. The private `core/`, `database/`, and `storage/` directories must remain outside the web root.
