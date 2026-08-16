<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/render.php';

echo render_newsletter_html(newsletter_payload((int) ($_GET['id'] ?? 0)), 'web');
