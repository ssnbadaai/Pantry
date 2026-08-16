# Self-Hosted Newsletter System

A cPanel-friendly PHP/MySQL newsletter CMS for creating visual newsletters, managing subscribers, publishing web versions, and sending queued email through your own domain SMTP.

## Included

- Admin setup, login, dashboard, newsletters, media, subscribers, and settings.
- Visual newsletter builder with sections, modular blocks, inline editing, drag-and-drop sorting, autosave, desktop/mobile preview, and send/test controls.
- In-builder image selection, upload, crop presets, rotate, reset, alt text, and non-destructive edited copies.
- Public archive, subscribe page, unsubscribe token flow, and web newsletter pages.
- Subscriber CSV import/export.
- SMTP settings, test email, email queue, and cron batch sender.
- Database schema and cPanel installation guide.

## Free Dependencies

The app keeps browser dependencies local in `assets/vendor` and uses Composer for PHPMailer:

- Bootstrap: MIT license.
- SortableJS: MIT license.
- Cropper.js: MIT license.
- PHPMailer: LGPL-2.1-only license.

No paid newsletter platform, paid image editor, paid API, or external database is required.

## Project Structure

```text
newsletter-system/
  admin/
  api/
  assets/
  cron/
  includes/
  templates/
  uploads/
  database.sql
  INSTALL.md
```

## First Run

Follow `INSTALL.md`, then visit `/newsletter/admin/setup.php`.

## Local Test With XAMPP

This workspace includes `includes/config.php` for local testing at `http://localhost:8080` against a local XAMPP database named `pantry_newsletter_local`.

Run the local PHP server from this folder:

```bash
C:\xampp\php\php.exe -S localhost:8080 local-router.php
```
