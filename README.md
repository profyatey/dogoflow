# DogoFlow 3.0

## Canonical production structure

This release uses `public_html/` as the only web-accessible directory. Keep `core/`, `database/`, and `storage/` private.

```text
DogoFlow/
├── core/                 # PRIVATE application code/config
├── database/             # PRIVATE SQL/schema/migrations
├── storage/              # PRIVATE logs/cache/private data
├── build/                # PRIVATE Tailwind source/build config
├── deploy/               # Deployment and launch documentation
└── public_html/          # WEB ROOT
    ├── index.php
    ├── admin/
    ├── auth/
    ├── customer/
    ├── seller/
    ├── affiliate/
    ├── api/
    ├── partials/
    ├── assets/
    ├── uploads/
    └── .htaccess
```

## XAMPP

Place the project at `C:\xampp\htdocs\DogoFlow`. Start Apache/MySQL, create/select database `dogo`, import `database/schema.sql`, then configure the local `.env` from `.env.example`.

For the first admin, edit `database/seed.sql` with your admin email and a PHP-generated password hash, import it, and never upload that seed containing credentials to the public web root.

Open:

`http://localhost/DogoFlow/public_html/`

The app detects this local base path automatically.

## AvesHost / cPanel-style shared hosting

Use this layout:

```text
/home/ACCOUNT/
├── core/
├── database/
├── storage/
└── public_html/
```

Upload `core/`, `database/`, and `storage/` outside the hosting web root. Upload the **contents** of this release's `public_html/` directory into the hosting `public_html/`.

Set production environment variables/private configuration for the database, `DOGOFLOW_ENV=production`, `DOGOFLOW_URL`, Paystack and SMTP. Production database credentials are intentionally not embedded in source.

## Database

For a clean install, import `database/schema.sql`. For an existing installation, apply only the migrations required for that installation and then apply `database/migration_production_hardening.sql` before final QA.

## Security

Never put `.env`, database passwords, SQL dumps, private logs or runtime cache files in the public web root. Product uploads are validated and the upload directory contains an `.htaccess` rule preventing server-side script execution.

## Release validation

See `PRODUCTION_REPAIR_NOTES.md` for the repair work performed against the 2026-10-06 production audit, and `deploy/LAUNCH_CHECKLIST.md` for the final live acceptance gate.
