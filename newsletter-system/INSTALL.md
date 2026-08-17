# Installation

1. Upload the contents of `newsletter-system` to your cPanel folder, for example `public_html/newsletter`.
2. Create a MySQL or MariaDB database in cPanel.
3. Create a database user and assign it all privileges for that database.
4. Import `database.sql` using phpMyAdmin.
5. Copy `includes/config.example.php` to `includes/config.php`.
6. Set `app_url`, database credentials, timezone, and `encryption_key` in `includes/config.php`.
7. Make these folders writable by PHP: `uploads/original`, `uploads/optimized`, and `uploads/thumbnails`.
8. Install PHPMailer on the server from inside the project folder with `composer install`, or upload a Composer-generated `vendor` folder.
9. Visit `/newsletter/admin/setup.php` and create the first administrator.
10. Open Settings and confirm the Google SMTP values. Defaults are prefilled for Gmail SMTP:
    - SMTP host: `smtp.gmail.com`
    - Port: `587`
    - Encryption: `TLS`
    - Sender/username: `hello@omqpro.com`
11. Paste the Google App Password for the sender account. This must be generated in Google; do not use the normal Google login password.
12. In cPanel DNS/email tools, configure SPF, DKIM, and DMARC for better deliverability.
13. Add a cPanel cron job such as:

```bash
php /home/USERNAME/public_html/newsletter/cron/send_queue.php
```

Run it every 1 to 5 minutes depending on your hosting provider email limits.

## Notes

- Do not send large lists in one request. The system queues individual messages and the cron job sends a configurable batch.
- The unsubscribe URL uses a random token, not a subscriber database ID.
- Uploaded originals are kept in `uploads/original`; cropped/optimized copies are created in `uploads/optimized`.
- If your server does not have PHP GD with JPEG/PNG/WEBP support, image upload and crop operations will fail. Enable GD or ImageMagick in cPanel PHP extensions.
